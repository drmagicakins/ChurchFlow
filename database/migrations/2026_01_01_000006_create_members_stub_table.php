<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deliberately minimal. Full Member Management (Phase 2, §10) adds photo,
 * family, department, custom fields, etc. This exists in Phase 1 purely as
 * a concrete tenant-owned model to prove BelongsToTenant/TenantScope work
 * end-to-end, and to give TenantIsolationTest something real to assert on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('church_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
