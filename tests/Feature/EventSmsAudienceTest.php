<?php

namespace Tests\Feature;

use App\Domains\Communication\Providers\SmsProviderInterface;
use App\Domains\Communication\Providers\SmsSendResult;
use App\Domains\Communication\Services\SmsWalletService;
use App\Domains\Events\Jobs\SendEventReminderSmsJob;
use App\Domains\Events\Services\EventSmsAudience;
use App\Domains\Communication\Services\PhoneNumber;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationSubmission;
use App\Models\Member;
use App\Models\Plan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The bulk SMS on an event must reach EVERYONE registered for it.
 *
 * The reported symptom was "Cannot send sms for event, I think it was removed".
 * Nothing had been removed: the audience was one table wide. It read the public
 * form's visitors only, so a church that registered its own members from inside
 * the app saw "Nobody who filled in the event form has a phone number yet" next
 * to a full roster, and the send button never appeared.
 *
 * These tests protect the rules that make the fix safe as well as working:
 *
 *  1. Members on the roster ARE texted (the bug).
 *  2. Visitors still are — the existing behaviour, unchanged.
 *  3. The number on the button equals the number of messages sent.
 *  4. A number that cannot be sent to is SEEN and reported, never passed to a
 *     provider that would charge for it and fail — and never silently lost.
 *  5. Nobody is texted twice, even when they are on both lists.
 *  6. SMS needs the permission, whoever holds it.
 */
class EventSmsAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $owner;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $this->church->id);

        $plan = Plan::factory()->create();
        Subscription::create([
            'church_id' => $this->church->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Church Owner']);

        foreach (['sms.manage', 'events.manage'] as $name) {
            $permission = Permission::firstOrCreate(
                ['name' => $name],
                ['group' => 'test', 'label' => ucfirst($name)]
            );
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $role->id, 'permission_id' => $permission->id,
            ]);
        }

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

        // Enough credits for the tests that actually send.
        app(SmsWalletService::class)->credit(
            app(SmsWalletService::class)->walletFor($this->church), 100, 'adjustment'
        );
    }

    private function member(string $name, ?string $phone): Member
    {
        return Member::create([
            'church_id' => $this->church->id,
            'full_name' => $name,
            'phone' => $phone,
            'status' => 'active',
        ]);
    }

    private function registerMember(Member $member): EventRegistration
    {
        return EventRegistration::create([
            'event_id' => $this->event->id,
            'member_id' => $member->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    }

    private function visitor(string $name, ?string $phone): EventRegistrationSubmission
    {
        return EventRegistrationSubmission::create([
            'event_id' => $this->event->id,
            'church_id' => $this->church->id,
            'name' => $name,
            'phone' => $phone,
            'status' => 'confirmed',
        ]);
    }

    /** A provider that records what it was asked to send. */
    private function fakeProvider(): object
    {
        $provider = new class implements SmsProviderInterface
        {
            public array $sent = [];

            public function send(string $toPhone, string $message, ?string $from = null): SmsSendResult
            {
                $this->sent[] = $toPhone;

                return new SmsSendResult(true, 'ref-'.count($this->sent));
            }
        };

        $this->app->instance(SmsProviderInterface::class, $provider);

        return $provider;
    }

    // --- the reported bug ---------------------------------------------------

    public function test_members_registered_for_the_event_are_in_the_sms_audience(): void
    {
        $member = $this->member('Bisi Emmanuel', '08123329900');
        $this->registerMember($member);

        $sendable = app(EventSmsAudience::class)->sendable($this->event);

        $this->assertCount(1, $sendable, 'A member on the roster must be an SMS recipient.');
        $this->assertSame('member', $sendable[0]['source']);
        $this->assertSame('+2348123329900', $sendable[0]['phone']);
    }

    public function test_the_event_page_offers_sms_when_only_members_are_registered(): void
    {
        $this->registerMember($this->member('Rosemary Fakinwa', '09155096645'));

        $response = $this->actingAs($this->owner)->get(route('events.show', $this->event));

        $response->assertOk();
        $response->assertSee('Bulk SMS reminder');
        $response->assertSee('Send SMS to 1');
        $response->assertDontSee('Nobody who filled in the event form has a phone number yet');
    }

    public function test_no_visitors_does_not_hide_the_sms_panel_any_more(): void
    {
        // The exact reported shape: a roster full of members, an empty public form.
        foreach (['Adamadu Chukwuemeka', 'Tunde Bakare'] as $name) {
            $this->registerMember($this->member($name, '08066676670'));
        }

        $this->actingAs($this->owner)->get(route('events.show', $this->event))
            ->assertOk()
            ->assertSee('Bulk SMS reminder')
            ->assertSee('Send SMS to');
    }

    // --- the audience itself -------------------------------------------------

    public function test_visitors_are_still_an_sms_audience(): void
    {
        $this->visitor('QA Visitor', '08000000001');

        $sendable = app(EventSmsAudience::class)->sendable($this->event);

        $this->assertCount(1, $sendable);
        $this->assertSame('visitor', $sendable[0]['source']);
    }

    public function test_the_audience_is_members_plus_visitors(): void
    {
        $this->registerMember($this->member('Bisi Emmanuel', '08123329900'));
        $this->visitor('Titi Bekeje', '07199000787');

        $sources = collect(app(EventSmsAudience::class)->sendable($this->event))->pluck('source')->sort()->values();

        $this->assertSame(['member', 'visitor'], $sources->all());
    }

    public function test_a_cancelled_registration_is_not_texted(): void
    {
        $registration = $this->registerMember($this->member('Cancelled Person', '08033344455'));
        $registration->update(['status' => 'cancelled']);

        $this->assertCount(0, app(EventSmsAudience::class)->sendable($this->event));
    }

    public function test_a_person_on_both_lists_is_only_texted_once(): void
    {
        $member = $this->member('Rosemary Fakinwa', '09155096645');
        $this->registerMember($member);
        $this->visitor('Rosemary Fakinwa', '09155096645');

        $audience = app(EventSmsAudience::class);
        $sendable = $audience->sendable($this->event);

        $this->assertCount(1, $sendable, 'The same number must not be texted twice.');
        $this->assertNotEmpty($audience->skipped($this->event));
    }

    // --- numbers that cannot be sent to --------------------------------------

    public function test_a_spreadsheet_damaged_number_is_skipped_with_a_reason(): void
    {
        $this->registerMember($this->member('Akeem Fadairo', '2.34906E+12'));
        $this->registerMember($this->member('Fine Number', '08123329900'));

        $audience = app(EventSmsAudience::class);

        $this->assertCount(1, $audience->sendable($this->event), 'Only the usable number may be texted.');
        $this->assertCount(1, $audience->skipped($this->event));

        $skipped = $audience->skipped($this->event)[0];

        $this->assertSame('Akeem Fadairo', $skipped['name']);
        $this->assertStringContainsString('scientific notation', $skipped['problem']);
    }

    public function test_a_member_with_no_phone_is_reported_rather_than_silently_dropped(): void
    {
        $this->registerMember($this->member('No Number', null));

        $audience = app(EventSmsAudience::class);

        $this->assertCount(0, $audience->sendable($this->event));
        $this->assertSame('no phone number', $audience->skipped($this->event)[0]['problem']);
    }

    public function test_the_page_names_who_cannot_be_texted_and_why(): void
    {
        $this->registerMember($this->member('Akeem Fadairo', '2.34906E+12'));
        $this->registerMember($this->member('Fine Number', '08123329900'));

        $this->actingAs($this->owner)->get(route('events.show', $this->event))
            ->assertOk()
            ->assertSee('Akeem Fadairo')
            ->assertSee('scientific notation')
            ->assertSee('cannot be texted');
    }

    // --- what the job actually sends ------------------------------------------

    public function test_the_job_texts_every_member_and_visitor_and_nobody_else(): void
    {
        $provider = $this->fakeProvider();

        $this->registerMember($this->member('Bisi Emmanuel', '08123329900'));
        $this->registerMember($this->member('Tunde Bakare', '08066676670'));
        $this->visitor('Titi Bekeje', '07199000787');
        $this->registerMember($this->member('Broken Number', '2.3481E+12'));

        (new SendEventReminderSmsJob($this->event->id, 'Reminder: see you on Sunday.'))->handle(
            $provider,
            app(SmsWalletService::class),
            app(\App\Domains\Communication\Services\SmsSegmentCalculator::class),
            app(EventSmsAudience::class),
        );

        $this->assertCount(3, $provider->sent, 'Two members and one visitor must be texted; the broken number must not be.');
        $this->assertNotContains('2.3481E+12', $provider->sent);
    }

    public function test_the_count_on_the_button_matches_the_messages_sent(): void
    {
        $this->registerMember($this->member('Bisi Emmanuel', '08123329900'));
        $this->registerMember($this->member('Tunde Bakare', '08066676670'));
        $this->visitor('Titi Bekeje', '07199000787');

        $onScreen = count(app(EventSmsAudience::class)->sendable($this->event));

        $provider = $this->fakeProvider();
        (new SendEventReminderSmsJob($this->event->id, 'Reminder.'))->handle(
            $provider,
            app(SmsWalletService::class),
            app(\App\Domains\Communication\Services\SmsSegmentCalculator::class),
            app(EventSmsAudience::class),
        );

        $this->assertSame($onScreen, count($provider->sent));
    }

    public function test_sending_records_who_was_reminded_on_the_right_table(): void
    {
        $member = $this->member('Bisi Emmanuel', '08123329900');
        $registration = $this->registerMember($member);
        $visitor = $this->visitor('Titi Bekeje', '07199000787');

        $provider = $this->fakeProvider();
        (new SendEventReminderSmsJob($this->event->id, 'Reminder.'))->handle(
            $provider,
            app(SmsWalletService::class),
            app(\App\Domains\Communication\Services\SmsSegmentCalculator::class),
            app(EventSmsAudience::class),
        );

        $this->assertNotNull($registration->fresh()->sms_reminded_at, 'The MEMBER registration must be stamped.');
        $this->assertNotNull($visitor->fresh()->sms_reminded_at, 'The VISITOR submission must be stamped.');
    }

    public function test_units_are_taken_from_the_church_wallet(): void
    {
        $this->registerMember($this->member('Bisi Emmanuel', '08123329900'));

        $wallets = app(SmsWalletService::class);
        $before = $wallets->walletFor($this->church)->balance_units;

        $this->fakeProvider();
        (new SendEventReminderSmsJob($this->event->id, 'Reminder.'))->handle(
            app(SmsProviderInterface::class),
            $wallets,
            app(\App\Domains\Communication\Services\SmsSegmentCalculator::class),
            app(EventSmsAudience::class),
        );

        $this->assertLessThan($before, $wallets->walletFor($this->church)->balance_units);
    }

    // --- permissions ---------------------------------------------------------

    public function test_sending_needs_the_sms_permission(): void
    {
        $this->registerMember($this->member('Bisi Emmanuel', '08123329900'));

        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Events Only']);
        $permission = Permission::firstOrCreate(['name' => 'events.manage'], ['group' => 'test', 'label' => 'Events']);
        DB::table('permission_role')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permission->id]);

        $user = User::factory()->create(['church_id' => $this->church->id]);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->post(route('events.remind.sms', $this->event), ['message' => 'Hello'])
            ->assertForbidden();
    }

    public function test_the_owner_can_queue_the_reminder_and_is_told_the_count(): void
    {
        $this->registerMember($this->member('Bisi Emmanuel', '08123329900'));
        $this->registerMember($this->member('Tunde Bakare', '08066676670'));

        \Illuminate\Support\Facades\Queue::fake();

        $this->actingAs($this->owner)
            ->post(route('events.remind.sms', $this->event), ['message' => 'See you Sunday'])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($s) => str_contains($s, '2 recipient'));

        \Illuminate\Support\Facades\Queue::assertPushed(SendEventReminderSmsJob::class);
    }

    // --- the phone normaliser itself ------------------------------------------

    public function test_phone_normalisation_covers_the_shapes_real_data_has(): void
    {
        $phones = new PhoneNumber;

        $cases = [
            '08123329900' => '+2348123329900',
            '0803 123 4567' => '+2348031234567',
            '+2348123329900' => '+2348123329900',
            '002348123329900' => '+2348123329900',
            '(080) 6667-6670' => '+2348066676670',
        ];

        foreach ($cases as $raw => $expected) {
            $this->assertSame($expected, $phones->normalise($raw), "Failed normalising {$raw}");
        }

        foreach (['2.3481E+12', '2.34906E+12', '', 'n/a', 'abc'] as $bad) {
            $this->assertNull($phones->normalise($bad), "'{$bad}' must not be sendable.");
        }
    }
}
