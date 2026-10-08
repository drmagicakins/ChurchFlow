<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subvention_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('name'); // e.g. "September 2026"
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('open'); // open|closed
            $table->timestamps();

            $table->unique(['church_id', 'name']);
        });

        Schema::create('subvention_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('subvention_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->cascadeOnDelete();
            $table->foreignId('subvention_rule_set_id')->constrained()->restrictOnDelete();

            // Free-form income figures keyed by whatever the rule set's
            // rules reference via base_field — e.g.
            // {"general_tithe": "5000.00", "minister_tithe": "1200.00",
            //  "admin_base": "5000.00", "salary": "800.00"}
            $table->json('figures');

            // §17: Draft → Submitted → Under Review → Returned → Approved →
            // Rejected. 'returned' is a soft, resubmittable outcome distinct
            // from the final 'rejected' — see SubventionWorkflowService.
            $table->string('status')->default('draft');
            $table->string('approval_status')->default('not_required'); // mirrors the linked Approval

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('return_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['subvention_period_id', 'organizational_unit_id'], 'one_submission_per_unit_per_period');
            $table->index(['church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subvention_submissions');
        Schema::dropIfExists('subvention_periods');
    }
};
