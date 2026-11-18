<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The church PROFILE: logo, reply address, contact details.
 *
 * WHY THESE COLUMNS. A church's own emails carried no identity beyond a line of
 * text at the bottom, and a recipient who pressed "Reply" answered ChurchFlow's
 * platform sender — never the church, and never the pastor who wrote it. The
 * profile gives the church a face (the logo) and an answer-address (the owner
 * and the parish pastor), and these columns are where that lives.
 *
 * WHY THE LOGO IS A FOREIGN KEY TO `uploaded_files` RATHER THAN A PATH. Every
 * other image in this app goes through FileUploadService: content-sniffed MIME,
 * a random on-disk name, the private disk, the plan's storage quota. A bare
 * path string would be a second, unaudited way to put a file on that disk.
 * `nullOnDelete` means removing the file leaves the church without a logo
 * instead of pointing at a row that is gone.
 *
 * Additive and nullable: every existing church keeps NULL, which means "no logo
 * yet" and "fall back to the platform sender" — nothing is rewritten, and no
 * behaviour depends on the row existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            if (! Schema::hasColumn('churches', 'logo_upload_id')) {
                $table->foreignId('logo_upload_id')
                    ->nullable()
                    ->after('slug')
                    ->constrained('uploaded_files')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('churches', 'email')) {
                // The church's PUBLIC address (what members write to).
                $table->string('email')->nullable()->after('denomination');
            }

            if (! Schema::hasColumn('churches', 'reply_to_email')) {
                // Optional override for where replies go, when the owner wants
                // them at a shared inbox rather than their own mailbox.
                $table->string('reply_to_email')->nullable()->after('email');
            }

            if (! Schema::hasColumn('churches', 'phone')) {
                $table->string('phone', 40)->nullable()->after('reply_to_email');
            }

            if (! Schema::hasColumn('churches', 'website')) {
                $table->string('website')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('churches', 'address')) {
                $table->text('address')->nullable()->after('website');
            }
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            if (Schema::hasColumn('churches', 'logo_upload_id')) {
                $table->dropConstrainedForeignId('logo_upload_id');
            }

            foreach (['email', 'reply_to_email', 'phone', 'website', 'address'] as $column) {
                if (Schema::hasColumn('churches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
