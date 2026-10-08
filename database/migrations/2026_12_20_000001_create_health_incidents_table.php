<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 13 — the platform health registry.
 *
 * One row per distinct problem, not per occurrence. That distinction is the
 * whole design: a database that is down for an hour produces a cron check
 * every minute, and 60 rows saying "database unreachable" is an incident log
 * nobody can read. Sixty occurrences of one problem is one row with a
 * `occurrences` counter and a `last_seen_at`.
 *
 * A "distinct problem" is identified by `fingerprint` — check name + a
 * normalized message. Normalization matters: two database errors that differ
 * only in a row id or a timestamp are the SAME problem, and if the fingerprint
 * included those they would open a new row every minute. The fingerprint is
 * computed in HealthCheckService, and its rules are documented there.
 *
 * The lifecycle is:
 *
 *   first_seen_at   when the problem was first detected  (never changes)
 *   last_seen_at    most recent detection                (updates every check)
 *   occurrences     how many times it has been detected
 *   resolved_at     NULL while open; set when a check passes again
 *   resolution_note human-readable, written by the resolver
 *
 * `resolved_at` NULL is therefore the definition of "currently broken", and
 * the public health page reads exactly that. Resolved rows are KEPT — a
 * problem that happened and was fixed is history worth having, and deleting
 * it would make "has this happened before?" unanswerable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_incidents', function (Blueprint $table) {
            $table->id();

            // Which check failed: database|cache|queue|storage|scheduler|...
            $table->string('check_name');

            // What it looked like. Severe problems are surfaced first on the
            // public page; `info` exists so a non-fatal observation is not
            // dressed up as an outage.
            $table->string('severity')->default('error'); // info|warning|error|critical

            // Identity of the PROBLEM (not the occurrence) — see the class note.
            $table->string('fingerprint');

            // The most recent human-readable message, and the raw detail for
            // whoever has to debug it. Kept separate so the page can show one
            // and not the other.
            $table->text('message');
            $table->json('context')->nullable();

            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedInteger('occurrences')->default(1);

            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            // The open-incident query runs on every health page view, so it
            // gets an index. Partial-by-convention (the WHERE is on resolved_at
            // IS NULL) rather than a real partial index, which MySQL and
            // SQLite disagree about.
            $table->index(['resolved_at', 'severity']);
            $table->index('fingerprint');
            $table->index('first_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_incidents');
    }
};
