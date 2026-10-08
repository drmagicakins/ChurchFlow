<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            // Both nullable: a platform admin action or a pre-church event
            // (onboarding_started fires the moment a church is created,
            // which is the same transaction — church_id is always
            // available there, but the column stays nullable for events
            // that genuinely have no tenant, like a failed-login attempt
            // for an unknown email).
            $table->foreignId('church_id')->nullable()->constrained('churches')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('event_name'); // e.g. "onboarding_started", "tour_completed", "feature_used"
            // §55: "do not collect unnecessary personal information" —
            // properties are small, structured, product-usage facts (which
            // tour, which step, which feature key), never free text a
            // person typed or anything resembling PII.
            $table->json('properties')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_name', 'created_at']);
            $table->index(['church_id', 'event_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
