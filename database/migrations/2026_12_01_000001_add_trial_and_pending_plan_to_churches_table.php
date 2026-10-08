<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 — the 14-day trial.
 *
 * A trial is a church that has a real subscription row (so it is a fully
 * functional tenant from the first second) but has not been charged: the
 * subscription's status is `trialing` and `trial_ends_at` says when the
 * grace runs out. `churches.status` gets `trial`, which
 * EnsureSubscriptionAllowsAccess already treats as full access.
 *
 * `pending_plan_id` is what makes upgrade/downgrade honest during a trial.
 * Choosing a different plan while trialing does NOT change the plan the
 * church is using — that would silently move every plan limit (members,
 * branches, admins) mid-trial. It records the intent, and the plan is only
 * actually switched when the trial converts to a paid subscription. The
 * billing screen shows "Trialing Starter — switching to Growth on
 * <date>", which is what the person actually asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // §Trial: when the 14 free days end. Null once the trial has
            // converted (or for a subscription that never had one).
            $table->timestamp('trial_ends_at')->nullable()->after('current_period_end');

            // Set when the trial converts successfully, purely for support
            // ("when did this church actually start paying us?").
            $table->timestamp('trial_converted_at')->nullable()->after('trial_ends_at');

            // The plan the church has chosen to move to when the trial
            // converts. Null means "stay on the plan you picked at signup".
            $table->foreignId('pending_plan_id')->nullable()->after('plan_id')
                ->constrained('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['pending_plan_id']);
            $table->dropColumn(['pending_plan_id', 'trial_ends_at', 'trial_converted_at']);
        });
    }
};
