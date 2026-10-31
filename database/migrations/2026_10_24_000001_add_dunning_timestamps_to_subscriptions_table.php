<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §9/§34: dunning timing must be measured from the moment a subscription
 * ENTERED its status, not from its last write.
 *
 * RenewalAndDunningService used `updated_at` as a proxy for "when did this
 * enter past_due / grace_period". Any unrelated update to the row — a plan
 * note, an admin touch, a cancellation flag — reset that clock and silently
 * EXTENDED a delinquent church's access. These two columns make the clock
 * explicit and immune to unrelated writes.
 *
 * Additive only: existing rows keep NULL and the service falls back to
 * `updated_at` for them, so no subscription jumps state on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('past_due_at')->nullable()->after('cancelled_at');
            $table->timestamp('grace_period_started_at')->nullable()->after('past_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['past_due_at', 'grace_period_started_at']);
        });
    }
};
