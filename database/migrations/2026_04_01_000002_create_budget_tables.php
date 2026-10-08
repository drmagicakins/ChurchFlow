<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('financial_account_id')->nullable()
                ->constrained('financial_accounts')->nullOnDelete();
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft'); // draft|active|closed
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // matches a transaction's `category` string
            $table->decimal('planned_amount', 14, 2);
            $table->timestamps();

            $table->unique(['budget_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('budgets');
    }
};
