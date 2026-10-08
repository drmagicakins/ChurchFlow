<?php

namespace Tests\Feature;

use App\Domains\PastoralCare\Services\PastoralCareService;
use App\Models\Appointment;
use App\Models\Church;
use App\Models\Member;
use App\Models\PastoralCase;
use App\Models\Permission;
use App\Models\PrayerRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PastoralCareTest extends TestCase
{
    use RefreshDatabase;

    private function pastoralManager(Church $church): User
    {
        Permission::firstOrCreate(['name' => 'pastoral.manage'], ['group' => 'pastoral', 'label' => 'x']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Pastor']);
        $role->permissions()->attach(Permission::where('name', 'pastoral.manage')->first());
        $user->roles()->attach($role);

        return $user;
    }

    public function test_an_ordinary_admin_without_pastoral_permission_cannot_see_pastoral_cases_they_are_not_assigned_to(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $pastor = $this->pastoralManager($church);
        $ordinaryAdmin = User::factory()->create(['church_id' => $church->id]); // no permissions at all

        $member = Member::factory()->for($church, 'church')->create();
        $case = (new PastoralCareService())->openCase($member, 'counseling', $pastor);

        $this->assertFalse($ordinaryAdmin->can('view', $case), 'An ordinary administrator must never see a pastoral case they are not assigned to.');
        $this->assertTrue($pastor->can('view', $case));
    }

    public function test_a_pastor_without_the_manage_permission_can_still_see_only_their_own_assigned_cases(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $assignedPastor = User::factory()->create(['church_id' => $church->id]); // no pastoral.manage, just assigned
        $otherPastor = User::factory()->create(['church_id' => $church->id]);

        $member = Member::factory()->for($church, 'church')->create();
        $case = PastoralCase::create([
            'church_id' => $church->id, 'member_id' => $member->id, 'type' => 'welfare',
            'assigned_to' => $assignedPastor->id, 'opened_by' => $assignedPastor->id,
        ]);

        $this->assertTrue($assignedPastor->can('view', $case), 'A pastor must be able to see their own assigned case even without pastoral.manage.');
        $this->assertFalse($otherPastor->can('view', $case));

        $visible = PastoralCase::query()->visibleTo($assignedPastor)->get();
        $this->assertCount(1, $visible);
    }

    public function test_prayer_requests_follow_the_same_strict_visibility_rule(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $pastor = $this->pastoralManager($church);
        $ordinaryAdmin = User::factory()->create(['church_id' => $church->id]);
        $assignedVolunteer = User::factory()->create(['church_id' => $church->id]);

        $request = PrayerRequest::create([
            'church_id' => $church->id, 'submitted_by_name' => 'A visitor',
            'request' => 'Please pray for my family.', 'assigned_to' => $assignedVolunteer->id,
        ]);

        $this->assertTrue($pastor->can('view', $request));
        $this->assertTrue($assignedVolunteer->can('view', $request));
        $this->assertFalse($ordinaryAdmin->can('view', $request));
    }

    public function test_opening_a_case_creates_an_initial_note_and_notes_accumulate_without_ever_being_editable(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $pastor = $this->pastoralManager($church);
        $member = Member::factory()->for($church, 'church')->create();

        $service = new PastoralCareService();
        $case = $service->openCase($member, 'hospital_visit', $pastor, 'Admitted to General Hospital.');

        $this->assertCount(1, $case->notes);

        $service->addNote($case, $pastor, 'Visited on Tuesday, doing well.');
        $service->addNote($case, $pastor, 'Discharged.');

        $this->assertCount(3, $case->fresh()->notes);

        // There is deliberately no controller route to edit or delete a
        // PastoralCaseNote anywhere in this module (see PastoralCaseController)
        // — a correction is always a new note, never a rewrite of history.
        $noteRouteNames = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter()
            ->filter(fn ($name) => str_contains($name, 'pastoral.cases.notes'));

        $this->assertTrue($noteRouteNames->every(fn ($name) => $name === 'pastoral.cases.notes'), 'Only the create-a-note route should exist — no update/delete route for notes.');
    }

    public function test_closing_a_case_logs_a_final_note_and_sets_closed_at(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $pastor = $this->pastoralManager($church);
        $member = Member::factory()->for($church, 'church')->create();
        $case = (new PastoralCareService())->openCase($member, 'counseling', $pastor);

        $case->close('Resolved after three sessions.', $pastor);

        $case = $case->fresh();
        $this->assertSame('closed', $case->status);
        $this->assertNotNull($case->closed_at);
        $this->assertSame('Resolved after three sessions.', $case->notes->first()->note);
    }

    public function test_an_appointment_with_no_pastoral_case_is_visible_to_anyone_who_can_see_appointments(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $pastor = User::factory()->create(['church_id' => $church->id]);
        $ordinaryAdmin = User::factory()->create(['church_id' => $church->id]);

        $appointment = Appointment::create([
            'church_id' => $church->id, 'pastor_id' => $pastor->id,
            'title' => 'Building committee meeting', 'scheduled_at' => now()->addDay(),
        ]);

        $this->assertTrue($ordinaryAdmin->can('view', $appointment));
    }

    public function test_an_appointment_tied_to_a_pastoral_case_is_restricted_like_the_case_itself(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);

        $pastor = User::factory()->create(['church_id' => $church->id]);
        $ordinaryAdmin = User::factory()->create(['church_id' => $church->id]);
        $member = Member::factory()->for($church, 'church')->create();

        $case = PastoralCase::create([
            'church_id' => $church->id, 'member_id' => $member->id, 'type' => 'counseling', 'assigned_to' => $pastor->id,
        ]);

        $appointment = Appointment::create([
            'church_id' => $church->id, 'pastor_id' => $pastor->id, 'pastoral_case_id' => $case->id,
            'title' => 'Counseling session', 'scheduled_at' => now()->addDay(),
        ]);

        $this->assertTrue($pastor->can('view', $appointment));
        $this->assertFalse($ordinaryAdmin->can('view', $appointment));
    }

    public function test_a_church_cannot_see_another_churchs_pastoral_records(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $pastorA = $this->pastoralManager($churchA);
        app()->instance('tenant.church_id', $churchA->id);
        $memberA = Member::factory()->for($churchA, 'church')->create();
        (new PastoralCareService())->openCase($memberA, 'welfare', $pastorA);
        PrayerRequest::create(['church_id' => $churchA->id, 'request' => 'A request']);

        $pastorB = $this->pastoralManager($churchB);
        app()->instance('tenant.church_id', $churchB->id);
        $memberB = Member::factory()->for($churchB, 'church')->create();
        (new PastoralCareService())->openCase($memberB, 'welfare', $pastorB);
        PrayerRequest::create(['church_id' => $churchB->id, 'request' => 'B request']);

        app()->instance('tenant.church_id', $churchA->id);
        $this->assertCount(1, PastoralCase::all());
        $this->assertCount(1, PrayerRequest::all());
    }
}
