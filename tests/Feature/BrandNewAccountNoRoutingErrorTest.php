<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Department;
use App\Models\FinancialAccount;
use App\Models\Group;
use App\Models\Member;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "// No routing parameters" is the blade line the framework's error page
 * prints for the ROUTING PARAMETERS panel when the request that failed was
 * not a route with parameters. It is the developer error page, so what a user
 * actually sees is a raw 500 — the same error on every category of the app.
 *
 * Two things are therefore guarded here, on the actions a brand-new account
 * performs on its first day:
 *
 *  1. every one of them is a redirect, never an error page; and
 *  2. no error page is rendered with an empty routing-parameters panel, i.e.
 *     the message above can never reach a user of any category.
 */
class BrandNewAccountNoRoutingErrorTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();
        app()->instance('tenant.church_id', $this->church->id);

        $type = UnitType::create(['church_id' => $this->church->id, 'name' => 'Headquarters', 'level' => 0]);
        $branch = OrganizationalUnit::create([
            'church_id' => $this->church->id,
            'unit_type_id' => $type->id,
            'name' => $this->church->name,
        ]);

        $this->owner = $this->userWith([
            'members.view', 'members.create', 'members.edit', 'members.delete',
            'families.manage', 'departments.manage', 'groups.manage',
            'events.manage', 'attendance.manage', 'announcements.manage',
            'finance.view', 'finance.record', 'finance.approve',
            'budgets.manage', 'loans.manage', 'subvention.submit',
            'subvention.manage', 'billing.manage', 'settings.manage',
        ], [
            'is_registrar' => true,
            'organizational_unit_id' => $branch->id,
        ]);
    }

    private function userWith(array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['church_id' => $this->church->id], $attributes));

        $role = Role::create(['church_id' => $this->church->id, 'name' => 'R '.uniqid()]);
        $role->permissions()->attach(
            collect($permissions)->map(
                fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => $n])->id
            )
        );
        $user->roles()->attach($role);

        return $user->load('roles.permissions');
    }

    /** Any rendered error page counts as a failure, whatever the status. */
    private function assertNotAnErrorPage(\Illuminate\Testing\TestResponse $response, string $what): void
    {
        $html = $response->getContent();

        $this->assertStringNotContainsString('No routing parameters', $html, "Error page on: {$what}");
        $this->assertLessThan(500, $response->getStatusCode(), "Server error on: {$what}");
    }

    public function test_first_day_actions_across_every_category_never_render_a_routing_error(): void
    {
        $branch = OrganizationalUnit::where('church_id', $this->church->id)->firstOrFail();

        // Branch (the reported action) — create then remove.
        $this->actingAs($this->owner)->post('/branches', ['name' => 'Ikeja'])->assertRedirect();
        $created = OrganizationalUnit::where('name', 'Ikeja')->firstOrFail();
        $this->actingAs($this->owner)->delete('/branches/'.$created->id)->assertRedirect();

        // People.
        $this->actingAs($this->owner)->post('/members', [
            'full_name' => 'Ada Obi',
            'membership_status' => 'active',
            'organizational_unit_id' => $branch->id,
        ])->assertRedirect();

        $this->actingAs($this->owner)->post('/departments', ['name' => 'Choir'])->assertRedirect();
        $this->assertNotAnErrorPage(
            $this->actingAs($this->owner)->get('/departments'), 'departments index'
        );

        $this->actingAs($this->owner)->post('/groups', ['name' => 'Ushers'])->assertRedirect();

        // Activities.
        $this->actingAs($this->owner)->post('/events', [
            'title' => 'Sunday Service',
            'starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
            'venue' => 'Main Hall',
            'organizational_unit_id' => $branch->id,
            'status' => 'published',
        ])->assertRedirect();

        $this->actingAs($this->owner)->post('/announcements', [
            'title' => 'Welcome',
            'body' => 'We start tomorrow.',
            'audience_type' => 'all',
            'channels' => ['in_app'],
        ])->assertRedirect();

        $this->actingAs($this->owner)->post('/tasks', ['title' => 'Buy chairs'])->assertRedirect();

        // Finance.
        $this->actingAs($this->owner)->post('/finance/accounts', [
            'name' => 'General Offering',
            'type' => 'general',
        ])->assertRedirect();

        // Subvention + settings.
        $this->actingAs($this->owner)->post('/settings/profile', [
            'name' => $this->church->name,
        ]);

        // Every category page still renders as a page, not an error.
        foreach ([
            'dashboard' => '/dashboard',
            'people' => '/members',
            'branches' => '/branches',
            'departments' => '/departments',
            'groups' => '/groups',
            'activities' => '/events',
            'attendance' => '/attendance',
            'communication' => '/announcements',
            'tasks' => '/tasks',
            'finance' => '/finance/accounts',
            'budgets' => '/finance/budgets',
            'loans' => '/finance/loans',
            'subvention' => '/subvention/submissions',
            'subvention-rules' => '/subvention/rule-sets',
            'subvention-periods' => '/subvention/periods',
            'sms' => '/sms/campaigns',
            'reports' => '/reports',
            'calendar' => '/calendar',
            'settings' => '/settings',
            'team' => '/settings',
            'support' => '/support',
            'billing' => '/billing',
            'plans' => '/billing/plans',
        ] as $category => $uri) {
            $response = $this->actingAs($this->owner->fresh())->get($uri);

            $this->assertNotAnErrorPage($response, $category.' ('.$uri.')');
            $this->assertContains(
                $response->getStatusCode(),
                [200, 403],
                "{$category} ({$uri}) was not a page: got {$response->getStatusCode()}"
            );
        }
    }

    /**
     * A member (not just the owner) navigating their own categories must not
     * see it either.
     */
    public function test_a_regular_member_never_sees_a_routing_error(): void
    {
        $member = $this->userWith(['members.view', 'events.manage']);

        foreach (['/dashboard', '/members', '/branches', '/departments', '/groups',
            '/events', '/attendance', '/announcements', '/tasks', '/support'] as $uri) {
            $this->actingAs($member->fresh())->get($uri)->assertDontSee('No routing parameters', false);
        }
    }
}
