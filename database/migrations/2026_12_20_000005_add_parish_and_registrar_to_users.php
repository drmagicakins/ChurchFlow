<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §14/§37: a user belongs to a PARISH (an OrganizationalUnit). This is what
 * lets a pastor see only their own parish's information, while the registrar
 * (super-admin) sees every parish.
 *
 * `is_registrar` marks the church's registrar — the account that receives,
 * views and approves subventions submitted by pastors, and that may manage
 * (including delete) other users. It is separate from is_platform_admin,
 * which is the platform operator who owns no church.
 *
 * Both are nullable/defaulted so every existing user keeps working: a user
 * with no parish is simply "church-wide" and is not restricted by parish.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organizational_unit_id')->nullable()
                ->after('church_id')
                ->constrained('organizational_units')->nullOnDelete();

            $table->boolean('is_registrar')->default(false)->after('is_platform_admin');

            $table->index(['church_id', 'organizational_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['church_id', 'organizational_unit_id']);
            $table->dropConstrainedForeignId('organizational_unit_id');
            $table->dropColumn('is_registrar');
        });
    }
};
