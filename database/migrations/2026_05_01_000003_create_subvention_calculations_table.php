<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subvention_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subvention_submission_id')->constrained()->cascadeOnDelete();

            $table->decimal('retention_total', 14, 2);
            $table->decimal('deduction_total', 14, 2);
            $table->decimal('shortfall', 14, 2); // deduction_total - retention_total; can be negative (surplus)

            $table->foreignId('loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->decimal('loan_deduction_applied', 14, 2)->default(0);
            $table->decimal('remittance_amount', 14, 2); // what the branch must actually pay HQ, floor 0

            // Per-rule amounts at calculation time, e.g.
            // [{"rule":"General Tithe Retention","classification":"retention","amount":"500.00"}, ...]
            // Kept even if the rule set is edited later, so a past
            // calculation always shows exactly what was computed then —
            // never recomputed retroactively from today's rule set.
            $table->json('breakdown');

            // Set only once, at approval time, when a real LoanPayment
            // ledger entry is created for loan_deduction_applied — see
            // SubventionWorkflowService::approve(). Never set during a
            // plain (possibly re-run) calculation.
            $table->foreignId('loan_payment_id')->nullable()->constrained('loan_payments')->nullOnDelete();

            $table->timestamp('calculated_at')->useCurrent();
            $table->timestamps();

            $table->index('subvention_submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subvention_calculations');
    }
};
