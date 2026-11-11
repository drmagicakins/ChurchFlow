<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The shortfall direction was corrected: rows stored before the fix hold
 * (deductions − retention), rows stored after hold (retention − deductions).
 *
 * Until now the boundary was inferred from the data (the earliest row whose
 * shortfall happened to be >= 0), which misreads a pre-fix row that stored
 * exactly 0.00. This migration records the direction on each row instead, so
 * the boundary is a fact rather than a guess.
 *
 * Backfill: every row that already exists at the moment this migration runs
 * was written by the old engine, so it is marked 'deductions_minus_retention'.
 * Rows written from now on are marked 'retention_minus_deductions' by the
 * model's default. No stored amount is changed — only the marker is added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subvention_calculations', function (Blueprint $table) {
            $table->string('shortfall_direction', 32)
                ->default('retention_minus_deductions')
                ->after('shortfall');
        });

        // Everything that exists now predates the correction.
        DB::table('subvention_calculations')
            ->update(['shortfall_direction' => 'deductions_minus_retention']);
    }

    public function down(): void
    {
        Schema::table('subvention_calculations', function (Blueprint $table) {
            $table->dropColumn('shortfall_direction');
        });
    }
};
