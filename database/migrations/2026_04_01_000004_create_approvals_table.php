<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();

            // Polymorphic on purpose: this is the ONE approval engine
            // reused by Transaction (this phase) and, in Phase 5,
            // SubventionSubmission and LoanApplication — see
            // App\Domains\Approvals\Services\ApprovalWorkflow. Adding a new
            // approvable type never requires a new table.
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');

            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
            $table->index(['church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
