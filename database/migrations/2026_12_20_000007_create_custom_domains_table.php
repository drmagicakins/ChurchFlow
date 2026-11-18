<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §14: `custom_domain` is a Denomination-and-above feature that existed only as
 * a boolean in plans.features — there was nowhere to record the domain a
 * church had chosen, so the feature could not be delivered even to a church
 * paying for it.
 *
 * A church claims a hostname here. `status` tracks the honest state of that
 * claim (pending → verified), and `verification_token` is the value the church
 * publishes in a DNS TXT record. Nothing about a domain is trusted until the
 * token is seen in DNS, so a church cannot point traffic at a hostname it does
 * not control.
 *
 * `status` is a plain string (not an enum) for the same reason as every other
 * status column in this app: changing the set of states should not require a
 * schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();

            // Normalised (lowercase, no scheme, no trailing slash) before it is
            // stored — see CustomDomain::normalise(). Unique because two
            // churches routing the same hostname to different tenants is
            // exactly the ambiguity that must be impossible, not resolved by
            // "whichever row was created first".
            $table->string('hostname')->unique();

            $table->string('status')->default('pending'); // pending|verified|failed
            $table->string('verification_token', 64);

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error')->nullable();

            $table->timestamps();

            $table->index(['church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domains');
    }
};
