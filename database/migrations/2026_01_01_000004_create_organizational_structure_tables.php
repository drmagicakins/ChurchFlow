<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-tenant configurable labels: "Province", "Diocese", "Region"...
        // This is what keeps RCCG-specific (or any denomination's) terms
        // out of the schema and code entirely.
        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('name');            // e.g. "Province"
            $table->unsignedTinyInteger('level'); // 0 = top of this church's tree
            $table->timestamps();

            $table->unique(['church_id', 'name']);
        });

        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('unit_type_id')->constrained('unit_types')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('organizational_units')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable(); // free-text external code (e.g. migrated "AB001")
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'parent_id']);
            $table->unique(['church_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
        Schema::dropIfExists('unit_types');
    }
};
