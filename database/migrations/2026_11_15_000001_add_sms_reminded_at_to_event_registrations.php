<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a MEMBER registered for an event was texted about it.
 *
 * `event_registration_submissions` — the public form's visitors — has carried
 * `sms_reminded_at` since the reminder feature shipped. The member roster
 * (`event_registrations`) never did, because that roster was never an SMS
 * audience: the job read visitors only.
 *
 * Once members are included in the audience, the same record has to exist for
 * them, or "who has already been reminded" would be answerable for guests and
 * unanswerable for the church's own congregation.
 *
 * Additive and nullable: every existing row keeps a NULL, which already means
 * "not reminded". Nothing is read, rewritten or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event_registrations', 'sms_reminded_at')) {
            return; // already present (a database restored from a newer build)
        }

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->timestamp('sms_reminded_at')->nullable()->after('registered_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('event_registrations', 'sms_reminded_at')) {
            return;
        }

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('sms_reminded_at');
        });
    }
};