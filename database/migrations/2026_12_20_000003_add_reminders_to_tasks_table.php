<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §24: a task can carry a reminder. `due_date` is a date only (a deadline),
 * but a reminder needs a precise moment to fire, so it is its own nullable
 * datetime column rather than overloading due_date — a task can be due on
 * Friday and remind the assignee on Thursday evening.
 *
 * `reminder_sent_at` records when the in-app reminder was actually shown, so
 * the reminder popup fires exactly once per task and does not re-appear on
 * every page load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dateTime('remind_at')->nullable()->after('due_date');
            $table->dateTime('reminder_sent_at')->nullable()->after('remind_at');

            $table->index(['church_id', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['church_id', 'remind_at']);
            $table->dropColumn(['remind_at', 'reminder_sent_at']);
        });
    }
};
