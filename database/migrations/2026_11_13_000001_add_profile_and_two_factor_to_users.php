<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile picture + two-step authentication for every sign-in account.
 *
 * WHY `avatar_upload_id` POINTS AT uploaded_files INSTEAD OF A PATH.
 * Member photos already work this way (`members.photo_upload_id`): the file is
 * written to the private disk by FileUploadService and read back only through a
 * signed, time-limited URL. A plain `avatar_path` column would invite a public
 * path to a person's photograph, which is exactly what §23 forbids. Reusing the
 * same column shape means avatars inherit the storage-quota check, the
 * content-sniffed MIME allow-list and the signed-URL serving with no new code.
 *
 * TWO-STEP AUTHENTICATION COLUMNS.
 *   two_factor_secret     — the TOTP secret, ENCRYPTED at rest (Eloquent cast).
 *   two_factor_recovery_codes — JSON array of HASHED one-time codes.
 *   two_factor_confirmed_at — set only once the person has proved they can
 *                           generate a valid code. An unconfirmed secret must
 *                           never lock anyone out, so login challenges ONLY on
 *                           this column being non-null.
 *
 * Removing an avatar must not delete the person's row, so the foreign key is
 * nullOnDelete: deleting the file record simply leaves the user with initials.
 * Everything is nullable, so every existing user keeps working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('avatar_upload_id')->nullable()
                ->after('email')
                ->constrained('uploaded_files')->nullOnDelete();

            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('avatar_upload_id');
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
