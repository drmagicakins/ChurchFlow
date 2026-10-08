<?php

namespace Tests\Feature;

use App\Domains\Files\Services\FileUploadService;
use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile as TestUploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_a_valid_image_is_stored_with_a_random_filename_not_the_clients_filename(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $file = TestUploadedFile::fake()->image('definitely-not-random.jpg', 100, 100);

        $uploaded = (new FileUploadService())->storeImage($file, $church, $user);

        $this->assertStringNotContainsString('definitely-not-random', $uploaded->disk_path);
        $this->assertSame('definitely-not-random.jpg', $uploaded->original_filename);
        Storage::disk('private')->assertExists($uploaded->disk_path);
    }

    public function test_a_file_renamed_to_look_like_an_image_is_rejected_by_content_sniffing(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);

        // A text file given a .jpg extension and an image mime TYPE claim.
        // The bytes must be REAL non-image content on a stable path: create()
        // writes an empty/sparse file (Symfony's getMimeType() then falls back
        // to the client-supplied MIME type, so the assertion would pass for
        // the wrong reason), and createWithContent()'s temp file is cleaned up
        // before we can re-wrap it. The service must reject this because it
        // sniffs actual content, not the .jpg name or the claimed type.
        $path = tempnam(sys_get_temp_dir(), 'cf_upload_');
        file_put_contents($path, '<?php echo "not an image"; ?> this is plain text pretending to be a jpeg');
        $file = new TestUploadedFile($path, 'malicious.jpg', 'image/jpeg', null, true);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new FileUploadService())->storeImage($file, $church, $user);
    }

    public function test_an_oversized_file_is_rejected(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $file = TestUploadedFile::fake()->image('big.jpg')->size(6 * 1024); // 6MB > 5MB cap

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new FileUploadService())->storeImage($file, $church, $user);
    }

    public function test_a_disallowed_mime_type_is_rejected(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $file = TestUploadedFile::fake()->create('document.pdf', 10, 'application/pdf');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new FileUploadService())->storeImage($file, $church, $user);
    }

    public function test_member_photo_upload_sets_the_relation_and_is_retrievable_via_a_signed_url(): void
    {
        $church = Church::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['church_id' => $church->id]);
        app()->instance('tenant.church_id', $church->id);
        $member = \App\Models\Member::factory()->for($church, 'church')->create();

        // membership permission needed to update a member
        $perm = \App\Models\Permission::firstOrCreate(['name' => 'members.edit'], ['group' => 'x', 'label' => 'x']);
        $role = \App\Models\Role::create(['church_id' => $church->id, 'name' => 'Editor']);
        $role->permissions()->attach($perm);
        $user->roles()->attach($role);

        $file = TestUploadedFile::fake()->image('me.jpg');

        $this->actingAs($user)
            ->post(route('members.photo', $member), ['photo' => $file])
            ->assertRedirect();

        $member = $member->fresh();
        $this->assertNotNull($member->photo_upload_id);
        $this->assertStringContainsString('/files/', $member->photoUrl());
        $this->assertStringContainsString('signature=', $member->photoUrl());
    }

    public function test_a_church_cannot_download_another_churchs_file_even_with_a_validly_signed_url(): void
    {
        $churchA = Church::factory()->create(['status' => 'active']);
        $churchB = Church::factory()->create(['status' => 'active']);
        $userA = User::factory()->create(['church_id' => $churchA->id]);
        $userB = User::factory()->create(['church_id' => $churchB->id]);

        $file = TestUploadedFile::fake()->image('shared.jpg');
        $uploaded = (new FileUploadService())->storeImage($file, $churchA, $userA);

        $url = $uploaded->signedUrl();

        // The link is validly signed (it really was generated by this app)
        // but belongs to Church A. Route-model binding on {uploadedFile}
        // is itself tenant-scoped (UploadedFile uses BelongsToTenant), so
        // Church B's user gets a 404 before the controller's own explicit
        // ownership check even runs — isolation enforced one layer
        // earlier than the controller, not instead of it.
        $this->actingAs($userB)->get($url)->assertNotFound();
        $this->actingAs($userA)->get($url)->assertOk();
    }

    public function test_an_unsigned_or_tampered_url_is_rejected(): void
    {
        $church = Church::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $file = TestUploadedFile::fake()->image('x.jpg');
        $uploaded = (new FileUploadService())->storeImage($file, $church, $user);

        $this->actingAs($user)->get("/files/{$uploaded->id}")->assertForbidden();
    }

    public function test_a_church_cannot_see_another_churchs_uploaded_files_record(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();
        $userA = User::factory()->create(['church_id' => $churchA->id]);
        $userB = User::factory()->create(['church_id' => $churchB->id]);

        (new FileUploadService())->storeImage(TestUploadedFile::fake()->image('a.jpg'), $churchA, $userA);
        (new FileUploadService())->storeImage(TestUploadedFile::fake()->image('b.jpg'), $churchB, $userB);

        app()->instance('tenant.church_id', $churchA->id);
        $this->assertCount(1, \App\Models\UploadedFile::all());
    }
}
