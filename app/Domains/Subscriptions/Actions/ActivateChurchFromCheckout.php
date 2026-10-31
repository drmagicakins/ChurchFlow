<?php

namespace App\Domains\Subscriptions\Actions;

use App\Domains\Subscriptions\Events\ChurchActivated;
use App\Domains\Subscriptions\Services\SubscriptionService;
use App\Domains\Subscriptions\Services\TrialService;
use App\Models\Checkout;
use App\Models\Church;
use App\Models\Role;
use App\Models\UnitType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * §10-11: the ONLY place in the entire application a Church row is created
 * from a public signup. There is no other path from "someone registered"
 * to "a tenant exists" — registration (Phase 1's RegisterController) now
 * creates a User with no church_id at all; this action is what gives them
 * one, and only once their 14-day trial has been started.
 *
 * Idempotency (§33): if the checkout is already 'completed', this is a
 * no-op that returns the existing church — a redelivered webhook or a
 * doubly-clicked "verify" button can never create two churches or two
 * subscriptions for the same checkout. The provider_reference UNIQUE
 * constraint on `invoices` is the second, DB-enforced backstop.
 *
 * PHASE 11 — TRIAL FIRST, NO CHARGE AT SIGNUP
 * -------------------------------------------
 * This used to call `SubscriptionService::createInitial()`, which wrote an
 * `active` subscription and a `paid` invoice dated the moment of signup.
 * That is now wrong: the 14-day trial means no money changes hands at
 * signup at all. So this calls `TrialService::startTrial()` instead, which
 * writes a `trialing` subscription and NO invoice — an invoice for a
 * payment that never happened would be a false financial record, which is
 * exactly the kind of thing §48 exists to prevent.
 *
 * The `checkouts.purpose === 'subscription'` gate is unchanged, and the
 * checkout being marked `completed` still proves the plan choice was
 * recorded. What changed is that "completed" now means "trial started",
 * not "first month paid for".
 */
class ActivateChurchFromCheckout
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly TrialService $trials,
    ) {}

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
                'name' => $user->name."'s Church", // placeholder — the setup wizard collects the real name
                'slug' => Str::slug($user->name).'-'.Str::random(6),
                'status' => 'pending', // TrialService::startTrial flips this to 'trial' below
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

            // ...and NEVER leave the new owner with an empty role.
            //
            // This clone used to be best-effort: if the system-default role
            // was missing it simply cloned nothing, and signup still reported
            // success. That produced a church whose OWNER held zero
            // permissions — every Starter feature (members, families, groups,
            // events, attendance, finance, tasks, billing) answered 403 to the
            // only person who could have fixed it. Two churches in the dev
            // database were left in exactly that state.
            //
            // A missing catalogue is a deployment fault, so repair it the same
            // way the seeder would (it is idempotent: firstOrCreate + sync)
            // and clone from the role that actually exists afterwards.
            if (! $systemOwner) {
                (new \Database\Seeders\RolePermissionSeeder)->run();

                $systemOwner = Role::withoutGlobalScopes()
                    ->whereNull('church_id')
                    ->where('name', 'Church Owner')
                    ->first();
            }

            if ($systemOwner) {
                $ownerRole->permissions()->sync($systemOwner->permissions()->pluck('permissions.id'));
            }

            // Fail loudly rather than hand the customer a broken account: a
            // signup that cannot grant its owner the basic owner set is a
            // deployment error, and rolling the transaction back keeps the
            // half-built church out of the database entirely.
            abort_if(
                $ownerRole->permissions()->count() === 0,
                503,
                'The permission catalogue is not installed, so a new church cannot be given its owner access. Run php artisan db:seed --class=RolePermissionSeeder.'
            );

            $user->roles()->attach($ownerRole);

            // The church's default PARISH PASTOR role, created here rather than
            // on first visit, so the Team form offers it the first time the
            // owner opens the screen instead of an empty choice list. It is the
            // parish-only set from RolePermissionSeeder; nothing is granted to
            // anyone by creating it.
            \Database\Seeders\RolePermissionSeeder::ensureParishPastorRole($church->id);

            // PHASE 11: no charge at signup — 14 free days instead. This
            // writes a `trialing` subscription and deliberately NO invoice;
            // see the class docblock above. The plan the person chose is
            // recorded on the subscription and is what the trial runs on.
            $this->trials->startTrial($church, $plan, $checkout->billing_interval);

            // The account that registered the church IS the super admin — it
            // is the only one allowed to create other accounts (§14). Recorded
            // explicitly at the moment the church is created rather than left
            // to be inferred from "first user", which stops being a reliable
            // test as soon as a second account exists.
            $user->forceFill(['is_registrar' => true])->save();

            $checkout->update(['church_id' => $church->id, 'status' => 'completed']);

            ChurchActivated::dispatch($church->fresh());

            return $church->fresh();
        });
    }
}
