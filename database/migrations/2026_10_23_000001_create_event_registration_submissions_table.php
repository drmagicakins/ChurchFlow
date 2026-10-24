<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public event registration (§13) — the visitor-facing sign-up form.
 *
 * The existing event_registrations table requires a member_id, because it was
 * built for "a pastor registers someone already on the roll". The brief also
 * asks for a form a VISITOR can fill in without an account — someone with no
 * member record at all. A member_id is the wrong key for that person, so this
 * table holds the visitor's own details, keeps the event's public link, and
 * records what the organiser has sent them.
 *
 * Nothing is dropped or altered: `member_id` on event_registrations is left
 * exactly as it is, and the in-app roster keeps working off it. The event
 * detail page merges both sources, so a pastor sees one list of everybody
 * coming.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // The public slug a visitor's form lives behind. Nullable so every
            // existing event keeps working and simply has no public form until
            // its organiser shares one. Unique per church (the tenant scope
            // means the same slug can exist in two churches).
            $table->string('public_slug', 64)->nullable()->after('status');
            $table->boolean('registration_open')->default(true)->after('public_slug');

            $table->unique(['church_id', 'public_slug']);
        });

        Schema::create('event_registration_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            // The visitor. No account, no member record — just what they typed.
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('organization')->nullable(); // e.g. their church / school
            $table->unsignedSmallInteger('guests')->default(0);
            $table->text('notes')->nullable();

            $table->string('status')->default('registered'); // registered|waitlisted|cancelled
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('registered_at')->useCurrent();

            // Provenance: where did this person arrive from?
            $table->string('source')->default('public_form'); // public_form|import|manual

            // §16 "send event reminder" — when the organiser last mailed / texted
            // this person, so the screen can say "reminder sent 2 days ago"
            // rather than offering a button that would silently duplicate.
            $table->timestamp('email_reminded_at')->nullable();
            $table->timestamp('sms_reminded_at')->nullable();

            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['event_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_submissions');

        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['church_id', 'public_slug']);
            $table->dropColumn(['public_slug', 'registration_open']);
        });
    }
};
