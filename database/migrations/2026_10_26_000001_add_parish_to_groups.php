<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make "a group belongs to a parish" storable.
 *
 * Groups were church-owned only (`church_id`), so every pastor saw every group
 * in the church and a group created by one parish belonged to nobody in
 * particular. This adds the same parish column departments already carry.
 *
 * Three deliberate choices:
 *
 *  1. It is NULLABLE and NULL means "whole church" — the shape groups have
 *     today. Every existing row therefore keeps meaning exactly what it meant
 *     before; nothing is rewritten, nothing is deleted, no data is guessed.
 *  2. The foreign key is added by name, so `down()` can drop it by the same
 *     name on SQLite and MySQL.
 *  3. The unique index `UNIQUE(church_id, name)` is left exactly as it is. Two
 *     parishes under one church may not both own a "Youth" group yet; widening
 *     that index would change an existing guarantee, which is not what this
 *     task asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedBigInteger('organizational_unit_id')->nullable()->after('church_id');

            $table->foreign('organizational_unit_id', 'groups_organizational_unit_id_foreign')
                ->references('id')->on('organizational_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign('groups_organizational_unit_id_foreign');
            $table->dropColumn('organizational_unit_id');
        });
    }
};
