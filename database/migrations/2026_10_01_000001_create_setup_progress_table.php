<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per (church, step) rather than a JSON blob on churches —
        // the step catalog itself lives in code (see SetupWizardService),
        // not the database, so adding a new step later never needs a
        // migration; it just starts showing up as "not yet completed" for
        // every existing church.
        Schema::create('church_setup_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('step_key');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['church_id', 'step_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_setup_progress');
    }
};
