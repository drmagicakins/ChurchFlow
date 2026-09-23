<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('churches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('denomination')->nullable(); // free text, e.g. "RCCG" — never branches logic
            $table->string('timezone')->default('Africa/Lagos');
            $table->string('currency', 3)->default('NGN');
            $table->string('status')->default('pending'); // pending|active|past_due|grace_period|cancelled|expired|suspended
            $table->json('settings')->nullable(); // terminology overrides, feature flags, etc.
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('churches');
    }
};
