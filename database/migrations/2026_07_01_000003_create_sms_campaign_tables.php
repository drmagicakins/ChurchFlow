<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();

            $table->string('name');
            $table->text('message');

            // Same audience-targeting shape as Announcement (§19) — kept
            // consistent rather than inventing a second targeting model.
            $table->string('audience_type'); // church|branch|department|group
            $table->foreignId('organizational_unit_id')->nullable()
                ->constrained('organizational_units')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();

            $table->string('status')->default('draft'); // draft|queued|sending|completed|partially_failed|failed|cancelled
            $table->unsignedInteger('estimated_units')->nullable();
            $table->unsignedInteger('reserved_units')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['church_id', 'status']);
        });

        Schema::create('sms_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('phone');
            $table->unsignedTinyInteger('segments')->default(1);
            $table->string('status')->default('pending'); // pending|sent|failed
            $table->string('provider_message_id')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamps();

            $table->unique(['sms_campaign_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_campaign_recipients');
        Schema::dropIfExists('sms_campaigns');
    }
};
