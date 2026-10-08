<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('organizational_unit_id')->nullable()
                ->constrained('organizational_units')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('venue')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable(); // null = unlimited
            $table->json('speakers')->nullable(); // simple list of names for now
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('scheduled'); // scheduled|cancelled|completed
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'starts_at']);
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('registered'); // registered|waitlisted|cancelled
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();

            $table->unique(['event_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('events');
    }
};
