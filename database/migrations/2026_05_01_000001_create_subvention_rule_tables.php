<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subvention_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->string('name'); // e.g. "Group 1", "Group 2", "Standard Rate"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['church_id', 'name']);
        });

        Schema::create('subvention_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subvention_rule_set_id')->constrained()->cascadeOnDelete();

            $table->string('name'); // e.g. "General Tithe Retention"

            // Looked up in the submission's `figures` JSON — deliberately a
            // free string, not a fixed enum/column list, so a church can
            // define whatever income/expense fields its formula needs
            // without a migration. See SubventionSubmission.figures.
            $table->string('base_field')->nullable(); // null for type=fixed

            $table->string('type'); // percentage|fixed|capped_percentage
            $table->decimal('rate', 5, 2)->nullable();          // e.g. 10.00 = 10%
            $table->decimal('fixed_amount', 14, 2)->nullable(); // type=fixed
            $table->decimal('cap_amount', 14, 2)->nullable();   // type=capped_percentage ceiling

            // retention = counts toward what the branch keeps;
            // deduction = counts toward what's subtracted from retention to
            // find the shortfall the branch must remit. This pair is the
            // generalized shape of the legacy formula's
            // (general_calc + minister_calc) vs (admin_calc + salary).
            $table->string('classification'); // retention|deduction

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subvention_rules');
        Schema::dropIfExists('subvention_rule_sets');
    }
};
