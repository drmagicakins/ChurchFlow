<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('key');    // machine name, e.g. "t_shirt_size"
            $table->string('label');  // display name, e.g. "T-Shirt Size"
            $table->string('type')->default('text'); // text|number|date|select|boolean
            $table->json('options')->nullable(); // choices for type=select
            $table->timestamps();

            $table->unique(['church_id', 'key']);
        });

        Schema::create('member_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_custom_field_definition_id')
                ->constrained('member_custom_field_definitions')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'member_custom_field_definition_id'], 'member_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_custom_field_values');
        Schema::dropIfExists('member_custom_field_definitions');
    }
};
