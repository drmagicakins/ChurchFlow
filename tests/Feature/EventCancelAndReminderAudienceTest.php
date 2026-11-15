<?php

namespace Tests\Feature;

use App\Domains\Events\Services\EventCancellationImpact;
use App\Domains\Events\Services\EventReminderAudience;
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
 * Two things the brief asks for, both about telling people the truth:
 *
 *  1. "Cancel event" must ASK, and say what is at stake — especially when the
 *     event is running now or was created a day or two ago.
 *  2. Email and SMS must reach EVERY person registered for the event.
 */
class EventCancelAndReminderAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $owner;

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
            $permission = Permission::firstOrCreate(['name' => $name], ['group' => 'test', 'label' => $name]);
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $role->id, 'permission_id' => $permission->id,
            ]);
        }

        $this->owner = User::factory()->create(['church_id' => $this->church->id, 'is_registrar' => true]);
        $this->owner->roles()->sync([$role->id]);
    }

    private function event(array $attributes = []): Event
    {
        return Event::create(array_merge([
            'church_id' => $this->church->id,
            'title' => 'Sunday Service',
            'starts_at' => now()->addWeek(),
            'organizer_id' => $this->owner->id,
            'status' => 'scheduled',
        ], $attributes));
    }

    private function memberRegisteredOn(Event $event, string $name, ?string $phone, ?string $email): EventRegistration
    {
        $member = Member::create([
            'church_id' => $this->church->id,
            'full_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'status' => 'active',
        ]);

        return EventRegistration::create([
            'event_id' => $event->id,
            'member_id' => $member->id,
            'status' => 'registered',
            'registered_at' => now(),
        ]);
    }

    // --- the cancel confirmation --------------------------------------------

    public function test_the_cancel_button_asks_and_does_not_use_an_inline_handler(): void
    {
        $event = $this->event();

        $html = $this->actingAs($this->owner)->get(route('events.show', $event))->assertOk()->getContent();

        $this->assertStringContainsString('data-confirm=', $html, 'Cancelling must ask first.');
        $this->assertStringNotContainsString(
            'onsubmit="return confirm',
            $html,
            'An inline handler is blocked by the CSP, so it never runs — the app dialog must be used.'
        );
    }

    public function test_an_event_happening_right_now_says_so(): void
    {
        $event = $this->event([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $impact = app(EventCancellationImpact::class)->summarise($event);

        $this->assertSame('high', $impact['severity']);
        $this->assertStringContainsString('RIGHT NOW', implode(' ', $impact['reasons']));
    }

    public function test_an_event_created_a_day_ago_warns_that_it_may_be_a_mistake(): void
    {
        $event = $this->event();
        $event->forceFill(['created_at' => now()->subHours(20)])->save();

        $impact = app(EventCancellationImpact::class)->summarise($event->fresh());

        $this->assertSame('elevated', $impact['severity']);
        $this->assertStringContainsString('hour(s) ago', implode(' ', $impact['reasons']));
    }

    public function test_cancelling_names_the_registered_people_and_the_public_link(): void
    {
        $event = $this->event(['public_slug' => 'sunday-service', 'capacity' => 50]);
        $this->memberRegisteredOn($event, 'Bisi Emmanuel', '08123329900', 'bisi@example.test');

        $reasons = implode(' ', app(EventCancellationImpact::class)->summarise($event)['reasons']);

        $this->assertStringContainsString('1 person already registered', $reasons);
        $this->assertStringContainsString('public registration link stops working', $reasons);
    }

    public function test_the_dialog_gets_the_reasons_on_the_page_not_only_in_the_service(): void
    {
        $event = $this->event([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $this->actingAs($this->owner)->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('data-confirm-reasons', false)
            ->assertSee('RIGHT NOW', false);
    }

    // --- the email/SMS audience ----------------------------------------------

    public function test_an_email_reminder_reaches_registered_members_not_only_visitors(): void
    {
        $event = $this->event();
        $this->memberRegisteredOn($event, 'Bisi Emmanuel', '08123329900', 'bisi@example.test');
        EventRegistrationSubmission::create([
            'event_id' => $event->id, 'church_id' => $this->church->id,
            'name' => 'Titi Bekeje', 'email' => 'titi@example.test', 'status' => 'confirmed',
        ]);

        $emails = collect(app(EventReminderAudience::class)->emailRecipients($event))->pluck('email')->sort()->values();

        $this->assertSame(['bisi@example.test', 'titi@example.test'], $emails->all());
    }

    public function test_the_email_and_sms_audiences_are_the_same_people(): void
    {
        $event = $this->event();
        $this->memberRegisteredOn($event, 'Bisi Emmanuel', '08123329900', 'bisi@example.test');
        EventRegistrationSubmission::create([
            'event_id' => $event->id, 'church_id' => $this->church->id,
            'name' => 'Titi Bekeje', 'email' => 'titi@example.test', 'phone' => '07199000787', 'status' => 'confirmed',
        ]);

        $audience = app(EventReminderAudience::class);

        $this->assertCount(2, $audience->emailRecipients($event));
        $this->assertCount(2, $audience->smsRecipients($event));
    }

    public function test_the_email_reminder_button_counts_members(): void
    {
        $event = $this->event();
        $this->memberRegisteredOn($event, 'Bisi Emmanuel', '08123329900', 'bisi@example.test');

        $this->actingAs($this->owner)->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('1 person')
            ->assertDontSee('Nobody who filled in the event form has an email address yet');
    }

    public function test_queueing_the_email_reminder_reports_the_real_count(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $event = $this->event();
        $this->memberRegisteredOn($event, 'Bisi Emmanuel', '08123329900', 'bisi@example.test');
        $this->memberRegisteredOn($event, 'Tunde Bakare', '08066676670', 'tunde@example.test');

        $this->actingAs($this->owner)
            ->post(route('events.remind.email', $event))
            ->assertRedirect()
            ->assertSessionHas('status', fn ($s) => str_contains($s, '2 recipient'));
    }

    public function test_the_email_job_actually_queues_a_mail_to_a_member(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $event = $this->event();
        $this->memberRegisteredOn($event, 'Bisi Emmanuel', '08123329900', 'bisi@example.test');

        (new \App\Domains\Events\Jobs\SendEventReminderEmailsJob($event->id))
            ->handle(app(EventReminderAudience::class));

        \Illuminate\Support\Facades\Mail::assertQueued(
            \App\Mail\EventReminderMail::class,
            fn ($mail) => $mail->hasTo('bisi@example.test')
        );
    }

    public function test_the_reminder_mail_renders_for_a_member_with_no_visitor_row(): void
    {
        $event = $this->event();

        $mail = new \App\Mail\EventReminderMail($event, 'Bisi Emmanuel');

        $html = $mail->render();

        $this->assertStringContainsString('Hello Bisi Emmanuel', $html);
        $this->assertStringContainsString($event->title, $html);
    }
}