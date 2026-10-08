<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->nullable()
                ->constrained('organizational_units')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['church_id', 'organizational_unit_id', 'name']);
        });

        Schema::create('department_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable(); // e.g. "Head", "Assistant", null = ordinary member
            $table->timestamps();

            $table->unique(['department_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_member');
        Schema::dropIfExists('departments');
    }
};
