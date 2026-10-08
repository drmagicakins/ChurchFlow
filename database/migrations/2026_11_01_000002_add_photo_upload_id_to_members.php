<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Supersedes the Phase 2 `photo_path` column, which was added
            // as a schema placeholder before any upload handling existed.
            // A signed URL EXPIRES, so it must never be the thing stored
            // permanently — this FK is, and Member::photoUrl() mints a
            // fresh signed URL from it on every render instead.
            // `photo_path` is left in place (unused from here on) rather
            // than dropped, since dropping a column is a destructive,
            // harder-to-reverse migration than simply not writing to one.
            $table->foreignId('photo_upload_id')->nullable()->after('photo_path')
                ->constrained('uploaded_files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photo_upload_id');
        });
    }
};
