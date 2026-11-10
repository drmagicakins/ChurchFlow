<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Church;
use App\Models\Department;
use App\Models\Group;
use App\Models\OrganizationalUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §19: who may post an announcement to whom, and the 404 that made posting
 * one look broken.
 *
 * Reported: creating an announcement showed "404 | Not Found" when the
 * audience was the entire church, a group or a department — but not when a
 * specific branch was chosen.
 *
 * Cause: `announcements` carries an `organizational_unit_id` column, so
 * TenantScope narrows every query to the bound parish. A church-wide
 * announcement stores NULL there, so the redirect to `announcements.show`
 * after posting could not find the row it had just created. A branch
 * announcement stores the parish id, which is why only that case worked.
 *
 * The audience rules themselves were not enforced at all: any user with
 * `announcements.manage` could post to any audience, including another
 * branch.
 */
class AnnouncementAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private OrganizationalUnit $north;

    private OrganizationalUnit $south;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();
        app()->instance('tenant.church_id', $this->church->id);

        $type = UnitType::create(['church_id' => $this->church->id, 'name' => 'Branch', 'level' => 1]);

        $this->north = OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => 'North Parish',
        ]);
        $this->south = OrganizationalUnit::create([
            'church_id' => $this->church->id, 'unit_type_id' => $type->id, 'name' => 'South Parish',
        ]);
    }

    private function userWith(?OrganizationalUnit $parish, bool $registrar): User
    {
        $user = User::factory()->create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $parish?->id,
            'is_registrar' => $registrar,
        ]);

        $role = Role::create([
            'church_id' => $this->church->id,
            'name' => $registrar ? 'Church Owner' : 'Parish Pastor',
        ]);
        $role->permissions()->attach(
            collect(['announcements.manage'])->map(
                fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'communication', 'label' => $n])->id
            )
        );
        $user->roles()->attach($role);
        $user->load('roles.permissions');

        return $user;
    }

    private function pastor(): User
    {
        return $this->userWith($this->north, false);
    }

    private function owner(): User
    {
        return $this->userWith(null, true);
    }

    /** Bind the parish the way IdentifyTenant does, then act as the user. */
    private function actingAsInParish(User $user): static
    {
        app()->instance('tenant.church_id', $this->church->id);
        app()->instance('tenant.organizational_unit_id', $user->organizational_unit_id);

        return $this->actingAs($user);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Service time change',
            'body' => 'Sunday service starts at 10am this week.',
            'audience_type' => 'church_wide',
        ], $overrides);
    }

    // ---------------------------------------------------------------------
    // The 404
    // ---------------------------------------------------------------------

    /**
     * The reported 404, on the audience that still allows it.
     *
     * A church-wide announcement stores a NULL branch, which TenantScope hid
     * from a parish-scoped user — so the redirect after posting 404'd. Only the
     * church owner may post church-wide (see the audience rules below), so the
     * owner is the actor this test drives. The pastor's VIEW of a church-wide
     * announcement is covered separately by
     * test_a_church_wide_announcement_appears_in_a_parish_pastors_list.
     */
    public function test_the_owner_can_post_a_church_wide_announcement_without_a_404(): void
    {
        $owner = $this->owner();

        $response = $this->actingAsInParish($owner)->post(route('announcements.store'), $this->payload());

        $announcement = Announcement::withoutGlobalScopes()->latest('id')->first();

        $this->assertNotNull($announcement, 'The announcement was not created.');
        $this->assertSame('church_wide', $announcement->audience_type);
        $this->assertNull($announcement->organizational_unit_id);

        // The redirect target must actually open — this is the reported 404.
        $response->assertRedirect(route('announcements.show', $announcement));
        $this->actingAsInParish($owner)
            ->get(route('announcements.show', $announcement))
            ->assertOk();
    }

    /**
     * The same 404, seen from the parish side.
     *
     * A pastor cannot POST church-wide, but a church-wide announcement posted
     * by the owner must still open for them — the row has a NULL branch, which
     * is exactly the shape TenantScope used to hide. This is the half of the
     * original bug that a "pastor is refused" test would not catch.
     */
    public function test_a_parish_pastor_can_open_a_church_wide_announcement_posted_by_the_owner(): void
    {
        $owner = $this->owner();
        $pastor = $this->pastor();

        $this->actingAsInParish($owner)->post(route('announcements.store'), $this->payload());
        $announcement = Announcement::withoutGlobalScopes()->latest('id')->first();

        $this->actingAsInParish($pastor)
            ->get(route('announcements.show', $announcement))
            ->assertOk()
            ->assertSee($announcement->title);
    }

    public function test_a_parish_pastor_can_post_a_department_announcement_without_a_404(): void
    {
        $pastor = $this->pastor();
        $department = Department::create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->north->id,
            'name' => 'Ushering',
        ]);

        $response = $this->actingAsInParish($pastor)->post(route('announcements.store'), $this->payload([
            'audience_type' => 'department',
            'department_id' => $department->id,
        ]));

        $announcement = Announcement::withoutGlobalScopes()->latest('id')->first();
        $response->assertRedirect(route('announcements.show', $announcement));
        $this->actingAsInParish($pastor)
            ->get(route('announcements.show', $announcement))
            ->assertOk();
    }

    public function test_a_parish_pastor_can_post_a_group_announcement_without_a_404(): void
    {
        $pastor = $this->pastor();
        $group = Group::create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->north->id,
            'name' => 'Faith Cells',
        ]);

        $response = $this->actingAsInParish($pastor)->post(route('announcements.store'), $this->payload([
            'audience_type' => 'group',
            'group_id' => $group->id,
        ]));

        $announcement = Announcement::withoutGlobalScopes()->latest('id')->first();
        $response->assertRedirect(route('announcements.show', $announcement));
        $this->actingAsInParish($pastor)
            ->get(route('announcements.show', $announcement))
            ->assertOk();
    }

    public function test_a_church_wide_announcement_appears_in_a_parish_pastors_list(): void
    {
        $pastor = $this->pastor();

        Announcement::create([
            'church_id' => $this->church->id,
            'title' => 'Church-wide notice',
            'body' => 'Everyone should see this.',
            'audience_type' => 'church_wide',
        ]);

        $this->actingAsInParish($pastor)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Church-wide notice');
    }

    // ---------------------------------------------------------------------
    // The audience rules
    // ---------------------------------------------------------------------

    public function test_a_parish_pastor_cannot_post_to_the_entire_church(): void
    {
        $pastor = $this->pastor();

        $this->actingAsInParish($pastor)
            ->post(route('announcements.store'), $this->payload(['audience_type' => 'church_wide']))
            ->assertSessionHasErrors('audience_type');

        $this->assertSame(0, Announcement::withoutGlobalScopes()->count());
    }

    public function test_the_entire_church_audience_is_not_offered_to_a_parish_pastor(): void
    {
        $pastor = $this->pastor();

        $response = $this->actingAsInParish($pastor)->get(route('announcements.index'))->assertOk();

        // The option must be absent from the dropdown, and the form must not
        // open on a choice the controller would refuse.
        $response->assertDontSee('Entire church');
        $response->assertViewHas('audiences', fn ($a) => ! array_key_exists('church_wide', $a));
    }

    public function test_the_entire_church_audience_is_offered_to_the_church_owner(): void
    {
        $owner = $this->owner();

        $this->actingAsInParish($owner)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Entire church')
            ->assertViewHas('audiences', fn ($a) => array_key_exists('church_wide', $a));
    }

    public function test_a_parish_pastor_can_post_to_their_own_branch(): void
    {
        $pastor = $this->pastor();

        $this->actingAsInParish($pastor)
            ->post(route('announcements.store'), $this->payload([
                'audience_type' => 'branch',
                'organizational_unit_id' => $this->north->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $this->north->id,
            Announcement::withoutGlobalScopes()->latest('id')->first()->organizational_unit_id
        );
    }

    public function test_a_parish_pastor_cannot_post_to_another_branch(): void
    {
        $pastor = $this->pastor();

        $this->actingAsInParish($pastor)
            ->post(route('announcements.store'), $this->payload([
                'audience_type' => 'branch',
                'organizational_unit_id' => $this->south->id,
            ]))
            ->assertSessionHasErrors('organizational_unit_id');

        $this->assertSame(0, Announcement::withoutGlobalScopes()->count());
    }

    public function test_the_church_owner_can_post_to_any_branch(): void
    {
        $owner = $this->owner();

        $this->actingAsInParish($owner)
            ->post(route('announcements.store'), $this->payload([
                'audience_type' => 'branch',
                'organizational_unit_id' => $this->south->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $this->south->id,
            Announcement::withoutGlobalScopes()->latest('id')->first()->organizational_unit_id
        );
    }

    public function test_the_church_owner_can_post_to_the_entire_church(): void
    {
        $owner = $this->owner();

        $this->actingAsInParish($owner)
            ->post(route('announcements.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame('church_wide', Announcement::withoutGlobalScopes()->latest('id')->first()->audience_type);
    }

    public function test_only_the_chosen_audience_field_is_stored(): void
    {
        $pastor = $this->pastor();
        $department = Department::create([
            'church_id' => $this->church->id,
            'organizational_unit_id' => $this->north->id,
            'name' => 'Choir',
        ]);

        // The form keeps every field in the DOM, so a stale branch value can
        // arrive alongside a department audience. Only the chosen one may be
        // written, or the row would claim two targets at once.
        $this->actingAsInParish($pastor)->post(route('announcements.store'), $this->payload([
            'audience_type' => 'department',
            'department_id' => $department->id,
            'organizational_unit_id' => $this->north->id,
        ]));

        $announcement = Announcement::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame($department->id, $announcement->department_id);
        $this->assertNull($announcement->organizational_unit_id);
        $this->assertNull($announcement->group_id);
    }

    public function test_a_parish_pastor_cannot_target_another_churchs_department(): void
    {
        $pastor = $this->pastor();

        $otherChurch = Church::factory()->create();
        $foreign = Department::withoutGlobalScopes()->create([
            'church_id' => $otherChurch->id,
            'name' => 'Their Choir',
        ]);

        $this->actingAsInParish($pastor)
            ->post(route('announcements.store'), $this->payload([
                'audience_type' => 'department',
                'department_id' => $foreign->id,
            ]))
            ->assertSessionHasErrors('department_id');

        $this->assertSame(0, Announcement::withoutGlobalScopes()->count());
    }

    public function test_a_parish_pastor_cannot_open_another_churchs_announcement(): void
    {
        $pastor = $this->pastor();

        $otherChurch = Church::factory()->create();
        $foreign = Announcement::withoutGlobalScopes()->create([
            'church_id' => $otherChurch->id,
            'title' => 'Not yours',
            'body' => 'x',
            'audience_type' => 'church_wide',
        ]);

        $this->actingAsInParish($pastor)
            ->get(route('announcements.show', $foreign))
            ->assertNotFound();
    }
}
