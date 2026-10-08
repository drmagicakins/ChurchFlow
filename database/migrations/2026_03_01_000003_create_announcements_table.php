<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('body');

            // Targeting (§19): exactly one audience mechanism applies.
            // 'audience_type' = church|branch|department|group — the
            // matching *_id is set only when its type is selected.
            $table->string('audience_type')->default('church');
            $table->foreignId('organizational_unit_id')->nullable()
                ->constrained('organizational_units')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();

            $table->timestamp('publish_at')->nullable(); // null = publish immediately
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'publish_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
