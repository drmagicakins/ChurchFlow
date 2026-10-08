<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('pastoral_case_id')->nullable()->constrained('pastoral_cases')->nullOnDelete();
            $table->foreignId('pastor_id')->constrained('users')->cascadeOnDelete();

            $table->string('title');
            $table->dateTime('scheduled_at');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->string('location')->nullable();
            $table->string('status')->default('scheduled'); // scheduled|completed|cancelled|no_show
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'scheduled_at']);
            $table->index(['church_id', 'pastor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
