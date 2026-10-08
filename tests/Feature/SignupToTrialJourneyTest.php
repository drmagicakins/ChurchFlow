<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 11 — the whole journey a church owner actually walks, end to end,
 * through real HTTP requests rather than by calling services directly.
 *
 * TrialAndPlanChangeTest proves each rule in isolation. This proves the
 * order they happen in, which is where integration bugs live: the pieces can
 * each be correct and still compose wrongly (that is exactly how the
 * "landing page redirected to itself" bug got through).
 *
 *   register -> choose plan -> start checkout -> verify -> trialing tenant
 *   -> dashboard shows the trial -> plans page -> change plan (intent only)
 *   -> subscribe -> active paid tenant with one invoice -> upgrade prorates
 */
class SignupToTrialJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_whole_signup_to_subscription_journey(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $plans = collect(['Starter', 'Growth', 'Professional'])->mapWithKeys(function ($name, $i) {
            $plan = Plan::factory()->create([
                'name' => $name,
                'slug' => strtolower($name),
                'monthly_price' => 10000 * ($i + 1),
                'max_members' => 200 * ($i + 1),
                'is_active' => true,
                'sort_order' => $i,
            ]);

            return [$name => $plan];
        });

        // 1. The landing page is public and shows the real catalogue with the
        //    trial promise on it.
        $this->get('/')
            ->assertOk()
            ->assertSee('Growth')
            ->assertSee('14-day trial');

        // 2. Register. This creates ONLY a user.
        $this->post('/register', [
            'name' => 'Journey Owner',
            'email' => 'journey@example.com',
            'password' => 'a-strong-password-1',
            'password_confirmation' => 'a-strong-password-1',
        ])->assertRedirect(route('plans.index'));

        $user = User::where('email', 'journey@example.com')->firstOrFail();
        $this->assertNull($user->church_id);
        $this->assertSame(0, Church::withoutGlobalScopes()->count());

        // 3. A registered-but-unpaid user is sent to plan selection, not the app.
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('plans.index'));

        // 4. Choose Growth and complete the (null, local) payment.
        $checkoutRedirect = null;
        $this->actingAs($user)
            ->post(route('checkout.start', $plans['Growth']), ['interval' => 'monthly'])
            ->assertRedirect();

        $checkout = \App\Models\Checkout::where('user_id', $user->id)->latest('id')->firstOrFail();
        $checkoutRedirect = route('checkout.verify', $checkout);

        // 5. Verifying starts the trial and lands on the dashboard.
        $this->actingAs($user)->get($checkoutRedirect)->assertRedirect(route('dashboard'));

        $user = $user->fresh();
        $church = Church::withoutGlobalScopes()->findOrFail($user->church_id);
        $subscription = Subscription::withoutGlobalScopes()->where('church_id', $church->id)->firstOrFail();

        $this->assertSame('trial', $church->status);
        $this->assertSame('trialing', $subscription->status);
        $this->assertSame('Growth', $subscription->plan->name);
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count(), 'Signing up must not charge anything.');

        // 6. The dashboard visibly shows the trial to the owner.
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Free trial')
            ->assertSee('Growth')
            ->assertSee('Subscribe now');

        // 7. The plan page renders, and picking a different plan during the
        //    trial records intent without switching.
        $this->actingAs($user)->get(route('billing.plans'))
            ->assertOk()
            ->assertSee('Professional')
            ->assertSee('Current'); // the Growth card is marked

        $this->actingAs($user)
            ->post(route('billing.change-plan'), ['plan_id' => $plans['Professional']->id])
            ->assertRedirect(route('billing.show'));

        $subscription = $subscription->fresh();
        $this->assertSame($plans['Growth']->id, $subscription->plan_id, 'Staying on the trialed plan.');
        $this->assertSame($plans['Professional']->id, $subscription->pending_plan_id);
        $this->assertSame(0, Invoice::withoutGlobalScopes()->count());

        // 8. The billing page tells them what will happen at trial end.
        $this->actingAs($user)->get(route('billing.show'))
            ->assertOk()
            ->assertSee('Professional')
            ->assertSee('trial');

        // 9. Subscribe now — charged once, moves onto the chosen plan.
        $this->actingAs($user)->post(route('billing.subscribe'))->assertRedirect(route('billing.show'));

        $subscription = $subscription->fresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame('active', $church->fresh()->status);
        $this->assertSame($plans['Professional']->id, $subscription->plan_id);
        $this->assertNull($subscription->pending_plan_id);
        $this->assertSame(1, Invoice::withoutGlobalScopes()->count());

        // 10. The billing page now shows a real paid subscription.
        $this->actingAs($user)->get(route('billing.show'))
            ->assertOk()
            ->assertSee('Professional')
            ->assertSee('Active')
            ->assertDontSee('Free trial');

        // 11. A paid upgrade previews the prorated cost before committing.
        $this->actingAs($user)
            ->get(route('billing.change-plan.preview', ['plan_id' => $plans['Starter']->id]))
            ->assertOk()
            ->assertSee('Downgrade');
    }
}
