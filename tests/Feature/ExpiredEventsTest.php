<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Event;
use App\Models\Member;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An event that has already happened.
 *
 * Two rules, and the link between them is the whole point of this file:
 *
 *  1. It offers a DELETE button — deleting is the action that is still useful
 *     for something on the calendar that will not happen again.
 *  2. Nobody can be registered for it — not a parish pastor, not the church
 *     owner. Adding a member to last Sunday's roster today would not be a
 *     registration; it would quietly falsify the attendance record.
 *
 * Rule 2 is asserted in two places on purpose. The screens hide the form
 * (presentation), and the controller refuses the POST (enforcement). Only the
 * second one survives a bookmarked tab, a stale browser history entry or a
 * double submit, so it is the one that must never regress.
 */
class ExpiredEventsTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $owner;

    private User $pastor;

    private OrganizationalUnit $parish;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->church = Church::factory()->create(['status' => 'active']);
        app()->instance('tenant.church_id', $this->church->id);

        $type = UnitType::create(['church_id' => $this->church->id, 'name' => 'Parish', 'level' => 1]);
        $this->parish = OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => 'Wisdom Parish',
        ]);

        $everyPermission = Permission::pluck('id');

        $this->owner = User::factory()->create(['church_id' => $this->church->id, 'is_registrar' => true]);
        $ownerRole = Role::firstOrCreate(['church_id' => null, 'name' => 'Church Owner']);
        $ownerRole->permissions()->sync($everyPermission);
        $this->owner->roles()->attach($ownerRole);

        $this->pastor = User::factory()->create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->parish->id,
        ]);
        $pastorRole = Role::create(['church_id' => $this->church->id, 'name' => 'Parish Pastor Test']);
        $pastorRole->permissions()->sync(Permission::where('name', 'events.manage')->pluck('id'));
        $this->pastor->roles()->attach($pastorRole);

        $this->member = Member::factory()->for($this->church, 'church')->create();
    }

    private function event(array $attributes = []): Event
    {
        return Event::create(array_merge([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->parish->id,
            'title' => 'Harvest Service',
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(2),
        ], $attributes));
    }

    // --- Expiry itself ------------------------------------------------------

    public function test_an_event_ends_at_its_end_time_and_falls_back_to_its_start_time(): void
    {
        $withEnd = $this->event(['starts_at' => now()->subDay(), 'ends_at' => now()->subHours(2)]);
        $this->assertTrue($withEnd->hasExpired());

        // No end time: a meeting the moment it starts.
        $withoutEnd = $this->event(['starts_at' => now()->subMinute(), 'ends_at' => null]);
        $this->assertTrue($withoutEnd->hasExpired());

        $upcoming = $this->event(['starts_at' => now()->addDay(), 'ends_at' => null]);
        $this->assertFalse($upcoming->hasExpired());
    }

    // --- Nobody can be registered ------------------------------------------

    public function test_the_church_owner_cannot_register_anyone_for_an_expired_event(): void
    {
        $event = $this->event();

        $this->actingAs($this->owner)
            ->post(route('events.register', $event), ['member_id' => $this->member->id])
            ->assertSessionHas('error');

        $this->assertSame(0, $event->registrations()->count(), 'A registration was written for an event that already happened.');
    }

    public function test_a_parish_pastor_cannot_register_anyone_for_an_expired_event(): void
    {
        $event = $this->event();

        $this->actingAs($this->pastor)
            ->post(route('events.register', $event), ['member_id' => $this->member->id])
            ->assertSessionHas('error');

        $this->assertSame(0, $event->registrations()->count());
    }

    public function test_a_future_event_still_registers_normally(): void
    {
        $event = $this->event(['starts_at' => now()->addDays(3), 'ends_at' => null]);

        $this->actingAs($this->owner)
            ->post(route('events.register', $event), ['member_id' => $this->member->id])
            ->assertSessionMissing('error');

        $this->assertSame(1, $event->registrations()->count(), 'The expiry rule must not block a live event.');
    }

    public function test_an_expired_events_roster_is_no_longer_edited(): void
    {
        $event = $this->event();
        $event->registrations()->create(['member_id' => $this->member->id, 'status' => 'registered']);

        $this->actingAs($this->owner)
            ->post(route('events.registrations.cancel', $event), ['member_id' => $this->member->id])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('event_registrations', [
            'event_id' => $event->id, 'member_id' => $this->member->id, 'status' => 'registered',
        ]);
    }

    public function test_the_public_form_stops_taking_sign_ups_once_the_event_has_happened(): void
    {
        $event = $this->event(['public_slug' => 'harvest-service', 'registration_open' => true]);

        $this->get(route('events.public.register', 'harvest-service'))->assertStatus(410);

        $this->post(route('events.public.register', 'harvest-service'), [
            'name' => 'A Visitor', 'email' => 'visitor@example.com',
        ])->assertStatus(410);

        $this->assertSame(0, $event->visitorRegistrations()->count());
    }

    /**
     * The visitor is TOLD, in the church's own page, and not shown a debug screen.
     *
     * Reported: opening the public link of an event that had happened showed
     * "// No request body" rather than the intended sentence. The cause was
     * `abort_if(..., 410, $message)`: the status and the message were right, but
     * Laravel renders a 4xx that carries a message through its EXCEPTION view, so
     * with APP_DEBUG on the visitor got "410 Gone", the exception class, a stack
     * trace, the request headers and an empty body section. The message was buried
     * in it, addressed to nobody.
     *
     * The assertions below are deliberately about the CONTENT a visitor reads, not
     * just the status — a status-only assertion is what let this ship.
     */
    public function test_the_expired_public_page_explains_itself_instead_of_showing_a_debug_screen(): void
    {
        $this->event(['title' => 'Harvest Service', 'public_slug' => 'harvest-service', 'registration_open' => true]);

        $response = $this->get(route('events.public.register', 'harvest-service'));

        // 410 Gone is kept: the link really is no longer available to a crawler.
        $response->assertStatus(410);

        $response->assertSee('This event has already taken place, so registration is closed.');
        $response->assertSee('Harvest Service');

        // Drawn on the guest shell, like the live registration form…
        $response->assertSee('cf-auth', false);
        // …and with no form on it, because the event is over.
        $response->assertDontSee('Register for this event');

        // None of the debug screen's furniture is present.
        $html = $response->getContent();
        $this->assertStringNotContainsString('No request body', $html);
        $this->assertStringNotContainsString('Stack trace', $html);
        $this->assertStringNotContainsString('<h1>Gone</h1>', $html);
        $this->assertStringNotContainsString('Symfony\\Component', $html);
    }

    /**
     * A stale form POSTed from an old tab gets the same explanation, not a 500.
     *
     * This is the case a real visitor hits: the page was open in a tab, the event
     * passed, they pressed Register. It must not answer with an exception document
     * either — and it must still write nothing.
     */
    public function test_a_stale_registration_post_for_a_finished_event_gets_the_same_explanation(): void
    {
        $event = $this->event(['public_slug' => 'harvest-service', 'registration_open' => true]);

        $response = $this->post(route('events.public.store', 'harvest-service'), [
            'name' => 'A Visitor', 'email' => 'visitor@example.com',
        ]);

        $response->assertStatus(410);
        $response->assertSee('This event has already taken place, so registration is closed.');
        $this->assertStringNotContainsString('No request body', $response->getContent());

        $this->assertSame(0, $event->visitorRegistrations()->count(), 'A stale POST wrote a registration.');
    }

    public function test_an_expired_event_cannot_be_opened_for_new_public_registrations(): void
    {
        // An event that ONCE had public registration: the refusal being tested
        // is "it cannot be opened", not "it was never opened".
        $event = $this->event(['public_slug' => 'harvest-service', 'registration_open' => false]);

        $this->actingAs($this->owner)
            ->post(route('events.public-form', $event))
            ->assertSessionHas('error');

        $this->assertFalse((bool) $event->fresh()->registration_open, 'An expired event was reopened for public sign-ups.');
    }

    // --- The Delete button --------------------------------------------------

    public function test_an_expired_event_offers_delete_on_the_events_list(): void
    {
        $event = $this->event(['title' => 'Last Week Harvest']);

        $response = $this->actingAs($this->owner)->get(route('events.index'));

        $response->assertOk()
            ->assertSee('Last Week Harvest')
            ->assertSee('Expired')
            ->assertSee(route('events.destroy', $event), false)
            ->assertSee('Delete');
    }

    public function test_a_future_event_does_not_offer_delete_on_the_events_list(): void
    {
        $event = $this->event([
            'title' => 'Coming Sunday', 'starts_at' => now()->addDays(2), 'ends_at' => null,
        ]);

        $html = $this->actingAs($this->owner)->get(route('events.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Coming Sunday', $html);
        $this->assertStringNotContainsString('Expired', $html);
        $this->assertFalse(
            $this->rowHasDeleteForm($html, $event->id),
            'A future event was offered a Delete button; only expired events should be.',
        );
    }

    /**
     * Does the table row for $eventId contain the delete form?
     *
     * Scoped to the ROW on purpose: the page shows the event's `show` link in
     * the same row, so "the page does not mention this event's URL" would be
     * false for every event, future or not.
     */
    private function rowHasDeleteForm(string $html, int $eventId): bool
    {
        if (! preg_match('#<tr>(?:(?!</tr>).)*?/events/'.$eventId.'(?:(?!</tr>).)*?</tr>#s', $html, $m)) {
            return false;
        }

        return str_contains($m[0], '/events/'.$eventId.'"')
            && substr_count($m[0], 'method="POST"') > 0
            && str_contains($m[0], 'DELETE');
    }

    public function test_deleting_an_expired_event_soft_deletes_it_and_keeps_its_history(): void
    {
        $event = $this->event();
        $event->registrations()->create(['member_id' => $this->member->id, 'status' => 'registered']);

        $this->actingAs($this->owner)
            ->delete(route('events.destroy', $event))
            ->assertRedirect(route('events.index'));

        $this->assertSoftDeleted('events', ['id' => $event->id]);
        // The registration row is untouched: deleting the event is not a
        // rewrite of who came.
        $this->assertDatabaseHas('event_registrations', ['event_id' => $event->id]);
    }

    public function test_the_detail_page_hides_registration_and_shows_delete_for_an_expired_event(): void
    {
        $event = $this->event(['title' => 'Old Convention']);

        $this->actingAs($this->owner)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('already taken place')
            ->assertSee('Delete event')
            ->assertDontSee('Register a member');
    }
}
