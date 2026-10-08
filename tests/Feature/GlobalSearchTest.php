<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Event;
use App\Models\FinancialAccount;
use App\Models\Member;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(Church $church, array $permissions): User
    {
        $user = User::factory()->create(['church_id' => $church->id]);
        if ($permissions !== []) {
            $ids = collect($permissions)->map(fn ($n) => Permission::firstOrCreate(['name' => $n], ['group' => 'x', 'label' => 'x'])->id);
            $role = Role::create(['church_id' => $church->id, 'name' => 'R']);
            $role->permissions()->attach($ids);
            $user->roles()->attach($role);
        }

        return $user->load('roles.permissions');
    }

    private function labels(array $json): array
    {
        return collect($json['groups'])->pluck('label')->all();
    }

    public function test_guests_cannot_search(): void
    {
        $this->getJson('/search/suggest?q=abc')->assertUnauthorized();
    }

    public function test_search_never_returns_another_churchs_records(): void
    {
        $a = Church::factory()->create();
        $b = Church::factory()->create();
        $user = $this->userWith($a, ['members.view']);

        app()->instance('tenant.church_id', $a->id);
        Member::factory()->for($a, 'church')->create(['full_name' => 'Zed Alpha']);
        app()->instance('tenant.church_id', $b->id);
        Member::factory()->for($b, 'church')->create(['full_name' => 'Zed Bravo']);

        $json = $this->actingAs($user)->getJson('/search/suggest?q=Zed')->assertOk()->json();

        $names = collect($json['groups'])->flatMap(fn ($g) => collect($g['items'])->pluck('title'))->all();
        $this->assertSame(['Zed Alpha'], $names);
    }

    public function test_sources_are_hidden_from_users_without_the_permission(): void
    {
        $church = Church::factory()->create();
        app()->instance('tenant.church_id', $church->id);
        Member::factory()->for($church, 'church')->create(['full_name' => 'Quill Member']);
        FinancialAccount::factory()->for($church, 'church')->create(['name' => 'Quill Fund']);
        Event::factory()->for($church, 'church')->create(['title' => 'Quill Night']);

        $none = $this->userWith($church, []);
        $labels = $this->labels($this->actingAs($none)->getJson('/search/suggest?q=Quill')->assertOk()->json());
        $this->assertSame(['Events'], $labels); // events are open to every signed-in user, like the Events page

        $finance = $this->userWith($church, ['finance.view']);
        $labels = $this->labels($this->actingAs($finance)->getJson('/search/suggest?q=Quill')->json());
        $this->assertContains('Finance accounts', $labels);
        $this->assertNotContains('Members', $labels);
    }

    public function test_like_wildcards_typed_by_the_user_are_treated_literally(): void
    {
        $church = Church::factory()->create();
        $user = $this->userWith($church, ['members.view']);
        app()->instance('tenant.church_id', $church->id);
        Member::factory()->for($church, 'church')->create(['full_name' => 'Plain Person']);

        $this->actingAs($user)->getJson('/search/suggest?q=%25%25')->assertOk()->assertJson(['groups' => []]);
    }

    public function test_short_queries_return_nothing_and_the_results_page_renders(): void
    {
        $church = Church::factory()->create();
        $user = $this->userWith($church, ['members.view']);

        $this->actingAs($user)->getJson('/search/suggest?q=a')->assertOk()->assertJson(['groups' => []]);
        $this->actingAs($user)->get('/search?q=a')->assertOk()->assertSee('Type at least 2 characters');
        $this->actingAs($user)->get('/search?q=nobody-here')->assertOk()->assertSee('No results');
    }
}
