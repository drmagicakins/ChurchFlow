<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 — platform-level notifications.
 *
 * `app_notifications.church_id` was NOT NULL with a foreign key to `churches`,
 * which encoded an assumption that every notification belongs to a tenant.
 * Platform admins break that assumption: they have `church_id = null` by
 * design (see the users migration — platform staff are deliberately never
 * implicitly a church user), so there was no value to put in the column.
 *
 * That mattered in practice: the health system has to be able to tell a
 * platform admin that the database is down, and before this migration there
 * was literally no row shape in which that message could be stored. The
 * notification centre worked perfectly for church users and could not address
 * a single platform admin.
 *
 * So church_id becomes nullable:
 *
 *   church_id set    -> a tenant notification (unchanged)
 *   church_id null   -> a platform notification, delivered to a platform admin
 *
 * Deliberately NOT solved by giving platform admins a dummy church: that would
 * make them a member of a tenant, which is the exact entanglement the platform-
 * admin flag exists to prevent, and it would leak platform notifications into
 * that church's notification queries.
 *
 * NOTE ON THE FOREIGN KEY. `nullOnDelete` rather than the cascade: if a church
 * is ever deleted, its notifications should lose the tenant link rather than
 * the rows vanishing, because a notification referencing a deleted church is
 * still a record that someone was told something.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropForeign(['church_id']);
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->foreignId('church_id')->nullable()->change();
            $table->foreign('church_id')->references('id')->on('churches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropForeign(['church_id']);
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            // Restoring NOT NULL requires there to be no platform rows left,
            // or the change fails — correctly, since silently deleting them
            // would lose data to make a schema change succeed.
            $table->foreignId('church_id')->nullable(false)->change();
            $table->foreign('church_id')->references('id')->on('churches')->cascadeOnDelete();
        });
    }
};
