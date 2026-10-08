<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            // null = general onboarding; otherwise a feature key this tour
            // introduces (§30's "✨ New Feature" prompts key off this).
            $table->string('feature')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tour_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('target_selector')->nullable(); // CSS selector/element id the frontend highlights
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('position')->default('bottom'); // top|bottom|left|right — frontend placement hint
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('action_url')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        // Global (not tenant-scoped: a User already belongs to exactly one
        // church, and a tour's progress is inherently personal, so scoping
        // through user_id is enough — no church_id needed here).
        Schema::create('user_tour_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('current_step')->default(0);
            // The tour's `version` at the moment this row last progressed —
            // §31: bump a tour's version on a real content change and a
            // user who already completed the OLD version sees it again,
            // without touching anyone still mid-tour on the current one.
            $table->unsignedInteger('tour_version_seen')->default(1);

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->boolean('dont_show_again')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'tour_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tour_progress');
        Schema::dropIfExists('tour_steps');
        Schema::dropIfExists('tours');
    }
};
