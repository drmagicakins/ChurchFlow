<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g. "finance_v2_enabled", "new_dashboard_enabled"
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_globally_enabled')->default(false);
            $table->timestamps();
        });

        // A row here OVERRIDES the global value for one church — used for
        // gradual rollouts (a handful of churches get it early) and beta
        // opt-ins, without flipping the flag on for everyone. Absence of a
        // row means "use the global value", not "disabled".
        Schema::create('feature_flag_church', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_flag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->boolean('is_enabled');
            $table->timestamps();

            $table->unique(['feature_flag_id', 'church_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_flag_church');
        Schema::dropIfExists('feature_flags');
    }
};
