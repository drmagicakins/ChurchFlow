<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Human-friendly, church-unique identifier (e.g. "GC-2026-0001"),
            // generated on create — see Member::booted() in the model.
            $table->string('membership_number')->nullable()->after('church_id');

            $table->foreignId('organizational_unit_id')->nullable()
                ->after('membership_number')
                ->constrained('organizational_units')->nullOnDelete();

            $table->string('photo_path')->nullable()->after('email');
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            // draft is used only for partially-completed CSV imports/invites
            $table->string('membership_status')->default('active');
            // active|inactive|visitor|transferred|deceased|draft

            $table->date('date_joined')->nullable();
            $table->date('baptism_date')->nullable();
            $table->boolean('is_worker')->default(false);
            $table->text('notes')->nullable();

            $table->index(['church_id', 'membership_status']);
            $table->unique(['church_id', 'membership_number']);
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizational_unit_id');
            $table->dropUnique(['church_id', 'membership_number']);
            $table->dropIndex(['church_id', 'membership_status']);
            $table->dropColumn([
                'membership_number', 'photo_path', 'gender', 'date_of_birth', 'phone',
                'address', 'emergency_contact_name', 'emergency_contact_phone',
                'membership_status', 'date_joined', 'baptism_date', 'is_worker', 'notes',
            ]);
        });
    }
};
