<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploaded_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            // What this file is attached to (a Member's photo, an Event's
            // cover image, a pastoral/document upload later) — polymorphic
            // so one upload pipeline serves every attachment point rather
            // than a bespoke one per feature.
            $table->string('attachable_type')->nullable();
            $table->unsignedBigInteger('attachable_id')->nullable();

            // The on-disk name is a random token, NEVER the user-supplied
            // filename (§23/§43: never trust what the client sent, and
            // never let a predictable path let someone guess another
            // tenant's file URL).
            $table->string('disk_path');
            $table->string('original_filename'); // kept for display/download only, never used to build a path
            $table->string('mime_type');          // the SNIFFED type, not the client-supplied one — see FileUploadService
            $table->unsignedBigInteger('size_bytes');
            $table->string('visibility')->default('private'); // private|public — private requires a signed URL

            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index('church_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploaded_files');
    }
};
