<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a MEMBER registered for an event was emailed about it.
 *
 * The companion of 2026_11_15_000001 (sms_reminded_at). Visitor submissions have
 * carried `email_reminded_at` since reminders shipped; the member roster never
 * did, because the roster was never an email audience — the job read visitor rows
 * only.
 *
 * Additive and nullable: existing rows keep NULL, which already means "not
 * reminded". Nothing is read back, rewritten or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event_registrations', 'email_reminded_at')) {
            return;
        }

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->timestamp('email_reminded_at')->nullable()->after('sms_reminded_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('event_registrations', 'email_reminded_at')) {
            return;
        }

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('email_reminded_at');
        });
    }
};