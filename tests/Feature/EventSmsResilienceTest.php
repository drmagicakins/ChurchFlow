<?php

namespace Tests\Feature;

use App\Domains\Communication\Providers\SmsProviderInterface;
use App\Domains\Communication\Providers\SmsSendResult;
use App\Domains\Communication\Services\SmsSegmentCalculator;
use App\Domains\Communication\Services\SmsWalletService;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Member;
use App\Models\Role;
use App\Models\SmsTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The EVENT-REMINDER path of the 2026-11-25 batch fix.
 *
 * The provider refuses, by design, a send it cannot make honest sense of (no
 * key, no approved sender), by throwing before a unit is spent. In this job that
 * throw escaped the recipient loop — the queue worker marked the job failed and
 * every reserved unit was stranded, with nothing on the ledger to explain it.
 *
 * Its own file, deliberately: PHPUnit binds ONE class per file, so a second
 * class appended to SmsBatchResilienceTest silently never runs (session
 * handoff §1y, mistake 1). The bulk-campaign half lives in that other file.
 */
class EventSmsResilienceTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $owner;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();
        app()->instance('tenant.church_id', $this->church->id);

        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Church Owner']);
        $this->owner = User::factory()->create([
            'church_id' => $this->church->id,
            'is_registrar' => true,
        ]);
        $this->owner->roles()->sync([$role->id]);

        $this->event = Event::create([
            'church_id' => $this->church->id,
            'title' => 'Supernatural Sunday',
            'starts_at' => now()->addWeek(),
            'organizer_id' => $this->owner->id,
            'status' => 'scheduled',
        ]);

        app(SmsWalletService::class)->credit(
            app(SmsWalletService::class)->walletFor($this->church), 100, 'adjustment'
        );
    }

    private function registerMember(string $name, string $phone): EventRegistration
    {
        $member = Member::create([
            'church_id' => $this->church->id,
            'full_name' => $name,
            'phone' => $phone,
            'status' => 'active',
        ]);

        return EventRegistration::create([
            'event_id' => $this->event->id,
            'member_id' => $member->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    }

    public function test_a_refused_event_reminder_refunds_everyone_instead_of_throwing(): void
    {
        $this->registerMember('Bisi Emmanuel', '08123329900');
        $this->registerMember('Tunde Bakare', '08066676670');

        $wallets = app(SmsWalletService::class);
        $before = $wallets->walletFor($this->church)->balance_units;

        $refusing = new class implements SmsProviderInterface
        {
            public function send(string $toPhone, string $message, ?string $from = null): SmsSendResult
            {
                throw new \RuntimeException('Termii is not configured: set TERMII_SENDER_ID in .env.');
            }
        };

        // Must NOT throw out of the job: a queue worker would mark it failed and
        // every reserved unit would be stranded.
        (new \App\Domains\Events\Jobs\SendEventReminderSmsJob($this->event->id, 'Reminder.'))->handle(
            $refusing,
            $wallets,
            app(SmsSegmentCalculator::class),
            app(\App\Domains\Events\Services\EventSmsAudience::class),
        );

        $this->assertSame(
            $before,
            $wallets->walletFor($this->church)->balance_units,
            'Every unit reserved for the refused reminder must be refunded.',
        );

        // The refund is on the ledger with the reason, not silently dropped.
        $refunds = SmsTransaction::query()->where('type', 'refund')->get();
        $this->assertCount(2, $refunds);
        $this->assertStringContainsString('TERMII_SENDER_ID', (string) $refunds->first()->description);
    }
}
