<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // System-wide catalog of permission *names* (e.g. "members.view").
        // Fixed by the platform — not editable per tenant. What varies per
        // tenant is which permissions compose a role.
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();  // e.g. "members.view"
            $table->string('group');           // e.g. "members", "finance"
            $table->string('label');           // human-readable, e.g. "View members"
            $table->timestamps();
        });

        // Roles ARE tenant-scoped and church-admin-editable, with a set of
        // system default roles (church_id null) that are cloned into a new
        // church on creation as a starting point.
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->nullable()->constrained('churches')->cascadeOnDelete();
            $table->string('name'); // e.g. "Finance Administrator"
            $table->boolean('is_system_default')->default(false);
            $table->timestamps();

            $table->unique(['church_id', 'name']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
