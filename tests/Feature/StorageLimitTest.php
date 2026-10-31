<?php

namespace Tests\Feature;

use App\Domains\Files\Services\FileUploadService;
use App\Domains\Subscriptions\Services\StorageLimitService;
use App\Models\Church;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UploadedFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile as TestUploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * §14: `plans.storage_mb` was seeded per tier but never read, so every church
 * had unlimited storage. These tests prove the ceiling exists, that it refuses
 * only the upload that crosses it, and that it never touches existing files.
 */
class StorageLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    /** A church on a plan with an explicit storage ceiling. */
    private function churchOnPlan(?int $storageMb, int $usedBytes = 0): Church
    {
        $church = Church::factory()->create(['status' => 'active']);
        $plan = Plan::factory()->create(['storage_mb' => $storageMb]);

        Subscription::create([
            'church_id' => $church->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        if ($usedBytes > 0) {
            UploadedFile::create([
                'church_id' => $church->id,
                'uploaded_by' => User::factory()->create(['church_id' => $church->id])->id,
                'disk_path' => 'churches/'.$church->id.'/existing.jpg',
                'original_filename' => 'existing.jpg',
                'mime_type' => 'image/jpeg',
                'size_bytes' => $usedBytes,
                'visibility' => 'private',
            ]);
        }

        return $church;
    }

    public function test_a_plan_with_no_storage_ceiling_allows_the_upload(): void
    {
        $church = $this->churchOnPlan(null);
        $user = User::factory()->create(['church_id' => $church->id]);

        $uploaded = (new FileUploadService())->storeImage(
            TestUploadedFile::fake()->image('photo.jpg', 20, 20),
            $church,
            $user,
        );

        $this->assertNotNull($uploaded->id);
    }

    public function test_an_upload_within_the_quota_is_allowed(): void
    {
        // 1 MB plan, 0 used, a tiny image.
        $church = $this->churchOnPlan(1);
        $user = User::factory()->create(['church_id' => $church->id]);

        $uploaded = (new FileUploadService())->storeImage(
            TestUploadedFile::fake()->image('small.jpg', 20, 20),
            $church,
            $user,
        );

        $this->assertNotNull($uploaded->id);
    }

    public function test_an_upload_that_would_exceed_the_quota_is_refused_and_writes_nothing(): void
    {
        // 1 MB plan, 1 MB already used -> any real file crosses the line.
        $church = $this->churchOnPlan(1, usedBytes: 1024 * 1024);
        $user = User::factory()->create(['church_id' => $church->id]);

        $before = UploadedFile::withoutGlobalScopes()->where('church_id', $church->id)->count();

        try {
            (new FileUploadService())->storeImage(
                TestUploadedFile::fake()->image('photo.jpg', 30, 30),
                $church,
                $user,
            );
            $this->fail('Expected the upload to be refused.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('storage limit', $e->getMessage());
        }

        // Nothing was recorded, and the existing file is untouched.
        $this->assertSame(
            $before,
            UploadedFile::withoutGlobalScopes()->where('church_id', $church->id)->count(),
        );
    }

    public function test_being_over_quota_never_deletes_existing_files(): void
    {
        // 10 MB of files on a 1 MB plan: already far over the ceiling.
        $church = $this->churchOnPlan(1, usedBytes: 10 * 1024 * 1024);

        $service = new StorageLimitService();
        $this->assertSame(10 * 1024 * 1024, $service->usedBytes($church));

        // The rows survive — restriction applies to NEW uploads only.
        $this->assertSame(
            1,
            UploadedFile::withoutGlobalScopes()->where('church_id', $church->id)->count(),
        );
    }

    public function test_usage_is_scoped_to_the_church(): void
    {
        $a = $this->churchOnPlan(1024, usedBytes: 2 * 1024 * 1024);
        $b = $this->churchOnPlan(1024, usedBytes: 3 * 1024 * 1024);

        $service = new StorageLimitService();

        $this->assertSame(2 * 1024 * 1024, $service->usedBytes($a));
        $this->assertSame(3 * 1024 * 1024, $service->usedBytes($b));
    }

    public function test_a_church_with_no_subscription_is_not_blocked(): void
    {
        // Mid-checkout / platform-admin contexts have no subscription yet.
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);

        $uploaded = (new FileUploadService())->storeImage(
            TestUploadedFile::fake()->image('photo.jpg', 20, 20),
            $church,
            $user,
        );

        $this->assertNotNull($uploaded->id);
    }
}
