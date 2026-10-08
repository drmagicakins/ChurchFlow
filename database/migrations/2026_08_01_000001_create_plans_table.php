<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');   // e.g. "Starter", "Growth", "Professional"
            $table->string('slug')->unique();
            $table->decimal('monthly_price', 10, 2);
            $table->decimal('yearly_price', 10, 2)->nullable();
            $table->string('currency', 3)->default('NGN');

            // §14: null = unlimited. Deliberately plain columns, not a JSON
            // blob, for the handful of limits the app actively enforces
            // (see PlanLimitService) — easy to query/compare in SQL when
            // needed. `features` below is JSON precisely for the long tail
            // of on/off flags (custom domains, API access, advanced
            // reports...) that the app only ever checks, never aggregates.
            $table->unsignedInteger('max_members')->nullable();
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_admins')->nullable();
            $table->unsignedInteger('storage_mb')->nullable();

            $table->json('features')->nullable(); // e.g. {"api_access": false, "custom_domain": false}

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
