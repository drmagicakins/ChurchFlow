<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();

            // The legacy app only ever loaned to a province (branch); a
            // real church platform may also loan to an individual member
            // (a welfare loan). Exactly one of these two should be set —
            // enforced in LoanService::create(), not at the DB level,
            // since "exactly one of two nullable FKs" isn't expressible as
            // a portable CHECK constraint across MySQL versions cleanly.
            $table->foreignId('organizational_unit_id')->nullable()
                ->constrained('organizational_units')->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();

            $table->decimal('principal_amount', 14, 2);
            $table->decimal('monthly_deduction', 14, 2);
            $table->decimal('interest_rate', 5, 2)->default(0); // percentage, e.g. 2.50 = 2.5%
            $table->string('status')->default('active'); // active|closed|defaulted
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'status']);
        });

        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['loan_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
        Schema::dropIfExists('loans');
    }
};
