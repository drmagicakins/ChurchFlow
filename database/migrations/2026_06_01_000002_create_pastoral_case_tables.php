<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pastoral_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();

            $table->string('type'); // counseling|welfare|hospital_visit|new_member_followup|other
            $table->text('description')->nullable();
            $table->string('status')->default('open'); // open|in_progress|closed
            $table->string('priority')->default('normal'); // low|normal|high

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'status']);
            $table->index(['church_id', 'assigned_to']);
        });

        // Append-only case log. Notes are never edited or deleted once
        // written — a correction is a new note, the same principle as
        // financial transactions in §48, applied here because a pastoral
        // record silently changing after the fact is its own kind of harm.
        Schema::create('pastoral_case_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pastoral_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->timestamp('created_at')->useCurrent();

            $table->index('pastoral_case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pastoral_case_notes');
        Schema::dropIfExists('pastoral_cases');
    }
};
