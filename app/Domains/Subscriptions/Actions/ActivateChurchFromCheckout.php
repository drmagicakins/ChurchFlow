<?php

namespace App\Domains\Subscriptions\Actions;

use App\Domains\Subscriptions\Events\ChurchActivated;
use App\Domains\Subscriptions\Services\SubscriptionService;
use App\Models\Checkout;
use App\Models\Church;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\UnitType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * §10-11: the ONLY place in the entire application a Church row is created
 * from a public signup. There is no other path from "someone registered"
 * to "a tenant exists" — registration (Phase 1's RegisterController) now
 * creates a User with no church_id at all; this action is what gives them
 * one, and only once a payment has been verified.
 *
 * Idempotency (§33): if the checkout is already 'completed', this is a
 * no-op that returns the existing church — a redelivered webhook or a
 * doubly-clicked "verify" button can never create two churches or two
 * subscriptions for the same checkout. The provider_reference UNIQUE
 * constraint on `invoices` is the second, DB-enforced backstop.
 */
class ActivateChurchFromCheckout
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(Checkout $checkout): Church
    {
        abort_unless($checkout->purpose === 'subscription', 422, 'This checkout is not a subscription checkout.');

        if ($checkout->status === 'completed') {
            return $checkout->church; // already processed — see class docblock
        }

        return DB::transaction(function () use ($checkout) {
            $checkout = Checkout::whereKey($checkout->id)->lockForUpdate()->first();

            if ($checkout->status === 'completed') {
                return $checkout->church;
            }

            $user = $checkout->user;
            $plan = $checkout->plan;

            $church = Church::create([
                'name' => $user->name."'s Church", // placeholder — the setup wizard (not built in this phase) collects the real name
                'slug' => Str::slug($user->name).'-'.Str::random(6),
                'status' => 'pending', // SubscriptionService::createInitial flips this to 'active' below
            ]);

            $headquarters = UnitType::create(['church_id' => $church->id, 'name' => 'Headquarters', 'level' => 0]);
            \App\Models\OrganizationalUnit::create([
                'church_id' => $church->id, 'unit_type_id' => $headquarters->id, 'name' => $church->name,
            ]);

            $user->update(['church_id' => $church->id]);

            $ownerRole = Role::firstOrCreate(['church_id' => $church->id, 'name' => 'Church Owner']);

            // Clone the system-default Church Owner role's permissions into
            // this church's own role (the Phase 1 seeder promised this
            // clone; without it the new owner would hold an empty role and
            // couldn't even open the billing page). Editing the church's
            // copy later never affects any other church.
            $systemOwner = Role::withoutGlobalScopes()
                ->whereNull('church_id')
                ->where('name', 'Church Owner')
                ->first();

            if ($systemOwner) {
                $ownerRole->permissions()->sync($systemOwner->permissions()->pluck('permissions.id'));
            }

            $user->roles()->attach($ownerRole);

            $this->subscriptions->createInitial($church, $plan, $checkout->billing_interval);

            // Copied verbatim from the checkout, NOT recalculated — the
            // person was quoted and charged this exact subtotal/tax/total
            // at checkout time (§4); the invoice must reflect that, even if
            // the platform's tax rate setting has since changed.
            Invoice::create([
                'church_id' => $church->id,
                'checkout_id' => $checkout->id,
                'type' => 'subscription',
                'amount' => $checkout->subtotal,
                'tax_amount' => $checkout->tax_amount,
                'tax_rate' => $checkout->tax_rate,
                'currency' => $checkout->currency,
                'status' => 'paid',
                'description' => "{$plan->name} plan — {$checkout->billing_interval}",
                'provider' => $checkout->provider,
                'provider_reference' => $checkout->provider_reference,
                'paid_at' => now(),
            ]);

            $checkout->update(['church_id' => $church->id, 'status' => 'completed']);

            ChurchActivated::dispatch($church->fresh());

            return $church->fresh();
        });
    }
}
