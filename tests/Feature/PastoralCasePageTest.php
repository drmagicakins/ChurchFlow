<?php

namespace Tests\Feature;

use App\Domains\PastoralCare\Services\PastoralCareService;
use App\Models\Appointment;
use App\Models\Church;
use App\Models\Member;
use App\Models\PastoralCase;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pastoral case page.
 *
 * It used to be a stub: a heading, "Status:" and a list of timestamps. The two
 * actions the controller already provided — add an entry, close the case — were
 * unreachable, and the page did not even say who the case was about beyond the
 * title.
 *
 * These tests assert the page is a real case view: it names the member, shows
 * the case facts and the append-only history, and gives back the note and
 * close actions. They also re-assert the module's privacy rule on the rendered
 * page, because a richer page is a bigger place to leak from.
 */
class PastoralCasePageTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();
        app()->instance('tenant.church_id', $this->church->id);
    }

    private function pastor(): User
    {
        $user = User::factory()->create(['church_id' => $this->church->id, 'name' => 'Pastor John']);
        $ids = collect(['members.view', 'pastoral.manage'])->map(
            fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'pastoral', 'label' => $n])->id
        );
        $role = Role::create(['church_id' => $this->church->id, 'name' => 'Pastor '.uniqid()]);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }

    private function openCase(User $pastor, array $memberAttributes = []): PastoralCase
    {
        $member = Member::factory()->for($this->church, 'church')->create(array_merge([
            'full_name' => 'Ronke Oshodi',
            'phone' => '+2348012345678',
        ], $memberAttributes));

        return app(PastoralCareService::class)
            ->openCase($member, 'counseling', $pastor, 'Struggling after a bereavement.');
    }

    public function test_the_page_shows_who_the_case_is_about_and_its_facts(): void
    {
        $pastor = $this->pastor();
        $case = $this->openCase($pastor);

        $html = $this->actingAs($pastor)->get(route('pastoral.cases.show', $case))->assertOk()->getContent();

        $this->assertStringContainsString('Ronke Oshodi', $html);
        $this->assertStringContainsString($case->member->membership_number, $html);
        $this->assertStringContainsString('Counseling', $html);
        $this->assertStringContainsString('Case details', $html);
        $this->assertStringContainsString('Struggling after a bereavement.', $html);
        $this->assertStringContainsString('Pastor John', $html); // assignment / opened by
        $this->assertStringContainsString('← All pastoral cases', $html);
    }

    public function test_the_history_is_shown_newest_first_with_its_authors(): void
    {
        $pastor = $this->pastor();
        $case = $this->openCase($pastor);

        app(PastoralCareService::class)->addNote($case, $pastor, 'Visited on Tuesday, doing well.');
        app(PastoralCareService::class)->addNote($case, $pastor, 'Agreed to weekly sessions.');

        $html = $this->actingAs($pastor)->get(route('pastoral.cases.show', $case->fresh()))->getContent();

        $this->assertStringContainsString('Visited on Tuesday, doing well.', $html);
        $this->assertStringContainsString('Agreed to weekly sessions.', $html);

        // Newest entry first: the later note appears before the earlier one.
        $newest = strpos($html, 'Agreed to weekly sessions.');
        $older = strpos($html, 'Visited on Tuesday, doing well.');
        $this->assertLessThan($older, $newest, 'The most recent entry should be listed first.');

        // The module's promise is visible on the page, not just in the code.
        $this->assertStringContainsString('append-only', $html);
        $this->assertStringContainsString('A correction is a new entry', $html);
    }

    public function test_adding_an_entry_from_the_page_works_and_is_not_editable(): void
    {
        $pastor = $this->pastor();
        $case = $this->openCase($pastor);

        $this->actingAs($pastor)
            ->post(route('pastoral.cases.notes', ['case' => $case]), [
                'note' => 'Prayed with the family.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $html = $this->actingAs($pastor)->get(route('pastoral.cases.show', $case->fresh()))->getContent();
        $this->assertStringContainsString('Prayed with the family.', $html);

        // The history offers no edit or delete control anywhere.
        $this->assertStringNotContainsString('pastoral.cases.notes.update', $html);
        $this->assertStringNotContainsString('pastoral.cases.notes.destroy', $html);
    }

    public function test_closing_a_case_records_the_outcome_and_stops_further_entries(): void
    {
        $pastor = $this->pastor();
        $case = $this->openCase($pastor);

        $this->actingAs($pastor)
            ->post(route('pastoral.cases.close', ['case' => $case]), [
                'closing_note' => 'Resolved after three sessions.',
            ])
            ->assertRedirect(route('pastoral.cases.index'));

        $case = $case->fresh();
        $this->assertSame('closed', $case->status);
        $this->assertNotNull($case->closed_at);

        $html = $this->actingAs($pastor)->get(route('pastoral.cases.show', $case))->getContent();

        $this->assertStringContainsString('This case is closed', $html);
        $this->assertStringContainsString('Resolved after three sessions.', $html);
        // No "add entry" form and no "close" button once it is closed…
        $this->assertStringNotContainsString('route-name="pastoral.cases.notes"', $html);
        $this->assertStringNotContainsString('data-toggle="case-close"', $html);
        // …but the history is still readable.
        $this->assertStringContainsString('Case history', $html);
    }

    public function test_the_page_lists_appointments_booked_for_the_case(): void
    {
        $pastor = $this->pastor();
        $case = $this->openCase($pastor);

        Appointment::create([
            'church_id' => $this->church->id,
            'pastoral_case_id' => $case->id,
            'pastor_id' => $pastor->id,
            'title' => 'Counseling session',
            'scheduled_at' => now()->addDays(2),
        ]);

        $html = $this->actingAs($pastor)->get(route('pastoral.cases.show', $case->fresh()))->getContent();

        $this->assertStringContainsString('Counseling session', $html);
        $this->assertStringContainsString('Appointments', $html);
    }

    /**
     * The richer page must not become a wider door: an administrator without
     * pastoral access still sees none of it.
     */
    public function test_a_case_stays_invisible_to_an_administrator_without_pastoral_access(): void
    {
        $pastor = $this->pastor();
        $case = $this->openCase($pastor);
        $ordinaryAdmin = User::factory()->create(['church_id' => $this->church->id]);

        $this->actingAs($ordinaryAdmin)->get(route('pastoral.cases.show', $case))->assertForbidden();
        $this->actingAs($ordinaryAdmin)
            ->post(route('pastoral.cases.notes', ['case' => $case]), ['note' => 'Should not be written'])
            ->assertForbidden();

        $this->assertCount(1, $case->fresh()->notes); // only the "Case opened" entry
    }

    public function test_another_church_cannot_open_the_case_page(): void
    {
        $pastor = $this->pastor();
        $case = $this->openCase($pastor);

        $otherChurch = Church::factory()->create();
        app()->instance('tenant.church_id', $otherChurch->id);
        $otherPastor = User::factory()->create(['church_id' => $otherChurch->id]);
        $role = Role::create(['church_id' => $otherChurch->id, 'name' => 'Pastor']);
        $role->permissions()->attach(
            Permission::firstOrCreate(['name' => 'pastoral.manage'], ['group' => 'pastoral', 'label' => 'x'])
        );
        $otherPastor->roles()->attach($role);
        $otherPastor->load('roles.permissions');

        $this->actingAs($otherPastor)->get(route('pastoral.cases.show', $case))->assertNotFound();
    }

    public function test_a_case_whose_member_was_removed_still_renders(): void
    {
        $pastor = $this->pastor();
        $case = $this->openCase($pastor);

        $case->member->delete(); // soft delete

        $html = $this->actingAs($pastor)->get(route('pastoral.cases.show', $case->fresh()))->getContent();

        $this->assertStringContainsString('Pastoral case', $html);
        $this->assertStringContainsString('Case history', $html);
    }
}
