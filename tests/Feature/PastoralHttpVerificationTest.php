<?php

namespace Tests\Feature;

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

/**
 * Throwaway verification: Phase 6 pastoral access control over the REAL HTTP stack
 * (auth + IdentifyTenant + controller + policy + Blade render), which is stronger
 * than the policy-object assertions in PastoralCareTest.
 *
 * The central claim of §21 is negative: an ordinary church administrator holding
 * full member/finance/subvention access must still see nothing here.
 */
class PastoralHttpVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function pastor(Church $church): User
    {
        Permission::firstOrCreate(['name' => 'pastoral.manage'], ['group' => 'pastoral', 'label' => 'Pastoral']);
        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Pastor']);
        $role->permissions()->attach(Permission::where('name', 'pastoral.manage')->first());
        $user->roles()->attach($role);

        return $user;
    }

    private function ordinaryAdmin(Church $church): User
    {
        $names = [
            'members.view', 'members.create', 'members.edit', 'members.delete',
            'families.manage', 'departments.manage', 'groups.manage',
            'finance.view', 'finance.record', 'finance.approve', 'budgets.manage', 'loans.manage',
            'subvention.submit', 'subvention.approve', 'subvention.manage',
            'events.manage', 'attendance.manage', 'announcements.manage', 'settings.manage',
        ];
        $ids = collect($names)->map(fn ($n) => Permission::firstOrCreate(
            ['name' => $n], ['group' => 'x', 'label' => 'x']
        )->id);

        $user = User::factory()->create(['church_id' => $church->id]);
        $role = Role::create(['church_id' => $church->id, 'name' => 'Church Admin']);
        $role->permissions()->attach($ids);
        $user->roles()->attach($role);

        // The Role model's global scope hides it from the tenant-scoped eager load,
        // so load permissions explicitly for hasPermission() to see them.
        $user->load('roles.permissions');

        return $user;
    }

    public function test_pastoral_access_control_over_real_http(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        $pastor = $this->pastor($church);
        $admin = $this->ordinaryAdmin($church);

        $member = Member::factory()->for($church, 'church')->create();
        $case = PastoralCase::create([
            'church_id' => $church->id, 'member_id' => $member->id,
            'type' => 'counseling', 'assigned_to' => $pastor->id, 'opened_by' => $pastor->id,
        ]);
        PrayerRequest::create([
            'church_id' => $church->id, 'request' => 'Pray for us',
            'submitted_by_name' => 'A visitor', 'assigned_to' => $pastor->id,
        ]);
        Appointment::create([
            'church_id' => $church->id, 'pastor_id' => $pastor->id,
            'title' => 'Building committee meeting', 'scheduled_at' => now()->addDay(),
        ]);

        // This admin is NOT under-privileged: verify the fixture is honest.
        $this->assertFalse($admin->hasPermission('pastoral.manage'));
        $this->assertTrue($admin->hasPermission('members.view'));
        $this->assertTrue($admin->hasPermission('finance.approve'));
        $this->assertTrue($admin->hasPermission('subvention.manage'));

        // Confirm which other admin areas this same admin CAN reach, so the
        // 403s below cannot be dismissed as "the fixture lacks all access".
        $this->actingAs($admin)->get('/members')->assertOk();
        $this->actingAs($admin)->get('/dashboard')->assertOk();

        // The negative core of §21.
        $this->actingAs($admin)->get('/pastoral/cases')->assertForbidden();
        $this->actingAs($admin)->get('/pastoral/cases/' . $case->id)->assertForbidden();
        $this->actingAs($admin)->get('/pastoral/prayer-requests')->assertForbidden();

        // Plain appointments are shared; that must still work for this admin.
        $this->actingAs($admin)->get('/pastoral/appointments')->assertOk();

        // The pastor sees everything they are entitled to.
        $this->actingAs($pastor)->get('/pastoral/cases')->assertOk();
        $this->actingAs($pastor)->get('/pastoral/cases/' . $case->id)->assertOk();
        $this->actingAs($pastor)->get('/pastoral/prayer-requests')->assertOk();
        $this->actingAs($pastor)->get('/pastoral/appointments')->assertOk();

        // And a guest is bounced to login rather than 403'd.
        $this->app['auth']->forgetGuards();
        $this->get('/pastoral/cases')->assertRedirect('/login');
    }

    public function test_pastoral_index_does_not_leak_another_churchs_cases(): void
    {
        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $pastorA = $this->pastor($churchA);
        $memberA = Member::factory()->for($churchA, 'church')->create();
        PastoralCase::create([
            'church_id' => $churchA->id, 'member_id' => $memberA->id,
            'type' => 'welfare', 'assigned_to' => $pastorA->id,
        ]);

        $pastorB = $this->pastor($churchB);
        $memberB = Member::factory()->for($churchB, 'church')->create();
        PastoralCase::create([
            'church_id' => $churchB->id, 'member_id' => $memberB->id,
            'type' => 'welfare', 'assigned_to' => $pastorB->id,
        ]);

        // churchA's pastor must not be able to fetch churchB's case by ID.
        // 404 (not 403) is the correct and stronger answer here: the tenant scope
        // removes the row entirely, so route-model binding never resolves it, and an
        // attacker learns nothing about whether that ID exists in another tenant.
        $caseB = PastoralCase::withoutGlobalScopes()->where('church_id', $churchB->id)->firstOrFail();
        $this->actingAs($pastorA)->get('/pastoral/cases/' . $caseB->id)->assertNotFound();

        $response = $this->actingAs($pastorA)->get('/pastoral/cases');
        $response->assertOk();
        $this->assertSame(1, PastoralCase::count(), 'churchA\'s pastor must only ever see churchA cases.');
    }
}
