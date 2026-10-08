<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('name'); // e.g. "The Doe Family"
            $table->timestamps();
            $table->softDeletes();

            $table->index('church_id');
        });

        Schema::create('family_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('relationship'); // head|spouse|child|dependent|other
            $table->timestamps();

            $table->unique(['family_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_member');
        Schema::dropIfExists('families');
    }
};
