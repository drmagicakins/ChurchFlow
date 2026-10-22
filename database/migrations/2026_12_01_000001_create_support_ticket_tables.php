<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            // Platform support staff only — never a church user, mirroring
            // the Phase 6 pastoral-care pattern of a narrow, specific
            // assignee rather than "whoever's logged in as platform admin
            // right now".
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('subject');
            $table->string('category'); // billing|technical|feature_request|other
            $table->text('description');
            $table->string('priority')->default('normal'); // low|normal|high|urgent
            $table->string('status')->default('open'); // open|in_progress|waiting_on_customer|resolved|closed

            $table->foreignId('screenshot_upload_id')->nullable()
                ->constrained('uploaded_files')->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'status']);
            $table->index(['status', 'priority']);
        });

        // Append-only, same principle as pastoral case notes (Phase 6) and
        // financial transactions (Phase 4): a reply is never edited or
        // deleted once posted — a correction is a new reply.
        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            // Internal notes are platform-staff-to-platform-staff — never
            // shown to the church that raised the ticket. See
            // SupportTicketPolicy/the reply views for where this is
            // actually enforced, not just stored.
            $table->boolean('is_internal_note')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('support_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_replies');
        Schema::dropIfExists('support_tickets');
    }
};
