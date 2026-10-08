<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The auth pages' contract.
 *
 * These views had been reduced to `<x-layout><h1>Sign In</h1></x-layout>` stubs by
 * an earlier local edit, so /login rendered the AUTHENTICATED shell — sidebar,
 * topbar, notification bell — around a sign-in form, and called
 * auth()->user()->name and ->roles on a visitor who has no user. Nobody could sign
 * in. LoginController, RegisterController and the routes were always correct; only
 * the two views were broken.
 *
 * The assertions below are deliberately about the SHELL, not about visual detail:
 * a guest page that renders the app chrome is the failure mode being locked out,
 * and it is invisible to a test that only asserts HTTP 200.
 */
class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_for_a_guest(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_register_page_renders_for_a_guest(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Create your account', false)
            ->assertSee('name="password_confirmation"', false);
    }

    /**
     * The regression that mattered: the guest shell must NOT be the app shell.
     * `cf-sidebar` only exists in components/layout.blade.php, which renders the
     * sidebar, the permission-filtered nav and the notification bell.
     */
    public function test_auth_pages_do_not_render_the_authenticated_app_shell(): void
    {
        foreach (['/login', '/register'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('cf-sidebar', $html, "{$url} rendered the app sidebar");
            $this->assertStringNotContainsString('cf-topbar', $html, "{$url} rendered the app topbar");
        }
    }

    public function test_auth_pages_use_the_guest_shell_and_real_brand_lockup(): void
    {
        foreach (['/login', '/register'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('cf-auth', false)        // the guest shell wrapper
                ->assertSee('cf-brandlogo', false);  // mark + live wordmark, not "CF" initials
        }
    }

    public function test_auth_pages_are_not_indexable(): void
    {
        foreach (['/login', '/register'] as $url) {
            $this->get($url)->assertOk()->assertSee('noindex', false);
        }
    }

    public function test_a_signed_in_user_can_sign_in_and_lands_on_the_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_is_rejected_and_does_not_authenticate(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'not-the-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_invalid_credentials_message_is_rendered_on_the_page(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        // The error must actually reach the visitor, not just the session bag.
        $this->get('/login')->assertOk()->assertSee('credentials do not match', false);
    }

    public function test_registration_creates_a_user_account_only_and_never_a_church(): void
    {
        $this->post('/register', [
            'name' => 'New Owner',
            'email' => 'new.owner@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('plans.index'));

        $user = User::where('email', 'new.owner@example.test')->first();

        $this->assertNotNull($user);
        // Brief §12: a church must not exist before payment is verified. church_id
        // stays null until ActivateChurchFromCheckout runs.
        $this->assertNull($user->church_id, 'registration provisioned a tenant before payment');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_a_mismatched_password_confirmation(): void
    {
        $this->from('/register')
            ->post('/register', [
                'name' => 'New Owner',
                'email' => 'mismatch@example.test',
                'password' => 'password',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'mismatch@example.test']);
    }

    public function test_a_signed_in_user_can_sign_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    /**
     * Regression: signing out must work for a user who has NOT yet been attached
     * to a church.
     *
     * /logout used to live inside the IdentifyTenant middleware group. For a user
     * with church_id === null, IdentifyTenant redirects to /plans BEFORE the
     * controller runs, so LoginController::destroy() never executed and the
     * session was never invalidated. The visitor stayed signed in — the nav kept
     * offering "Sign out" and /dashboard stayed reachable — from precisely the
     * state (registered, not yet paid) where someone is most likely to want to
     * abandon the account.
     *
     * The assertions below are about the SESSION, not the redirect: a redirect to
     * /plans is what the bug produced, and it is also what a working request could
     * plausibly produce, so the redirect target alone cannot distinguish them.
     */
    public function test_a_churchless_user_can_sign_out(): void
    {
        $user = User::factory()->create(['church_id' => null]);

        $this->actingAs($user)->post('/logout');

        $this->assertGuest('web');
    }

    public function test_a_churchless_user_is_actually_logged_out_afterwards(): void
    {
        $user = User::factory()->create(['church_id' => null]);

        $this->actingAs($user)->post('/logout');

        // A protected route must now bounce to /login, not to /plans (which is
        // where the broken build sent an still-authenticated user).
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_logout_does_not_run_tenant_resolution(): void
    {
        // The route must not carry IdentifyTenant, because that middleware can
        // redirect a church-less user away before the controller executes.
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->getName() === 'logout');

        $this->assertNotNull($route, 'logout route is missing');
        $this->assertNotContains(
            \App\Http\Middleware\IdentifyTenant::class,
            $route->gatherMiddleware(),
            'logout must not depend on tenant resolution'
        );
    }
}
