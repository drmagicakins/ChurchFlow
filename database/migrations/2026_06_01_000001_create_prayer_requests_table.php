<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prayer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();

            // Nullable: a prayer request can come from a visitor who isn't
            // a Member record yet — captured by name only in that case.
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('submitted_by_name')->nullable();

            $table->text('request');
            $table->boolean('is_confidential')->default(true);
            $table->string('status')->default('open'); // open|praying|answered|closed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prayer_requests');
    }
};
