<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('name'); // e.g. "General Fund", "Building Fund"
            $table->string('type')->default('general'); // general|building|missions|welfare|other
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['church_id', 'name']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->cascadeOnDelete();

            // income|expense|transfer_in|transfer_out|donation. A transfer
            // between two accounts is recorded as a linked pair of rows
            // (transfer_group_id), never as one row that silently touches
            // two balances — every row's amount contributes to exactly one
            // account's balance, which keeps the "sum of transactions =
            // account balance" invariant trivially true.
            $table->string('type');
            $table->string('category')->nullable(); // e.g. "Tithe", "Rent", "Salaries"
            $table->decimal('amount', 14, 2); // always stored positive; sign is implied by type
            $table->text('description')->nullable();
            $table->date('transacted_on');

            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete(); // donor, if applicable
            $table->uuid('transfer_group_id')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            // Denormalized cache of the linked Approval's status, kept in
            // sync exclusively by ApprovalWorkflow — never written directly
            // by a controller. not_required = auto-approved on creation
            // (income/transfers); pending/approved/rejected mirror the
            // approvals table for fast filtering without a join.
            $table->string('approval_status')->default('not_required');

            $table->boolean('is_void')->default(false);
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['church_id', 'financial_account_id', 'transacted_on']);
            $table->index(['church_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('financial_accounts');
    }
};
