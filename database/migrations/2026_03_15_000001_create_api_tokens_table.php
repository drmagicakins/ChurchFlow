<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §46/§14: church-issued API tokens for the versioned public surface.
        //
        // Deliberately NOT Sanctum: this app has exactly one token shape (a
        // church-scoped, read-only-at-launch key for server-to-server
        // integrations), and a bespoke table keeps the tenancy model in one
        // place — church_id is a first-class column here and in every scope,
        // the same way every other tenant-owned table works.
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');                      // human label: "Website sync"
            // The SHA-256 hash of the plaintext token. The plaintext is shown
            // ONCE at creation and never stored — a leaked database must not
            // hand out working keys (same rule as a password, and for the same
            // reason).
            $table->string('token_hash', 64)->unique();
            // First 8 chars of the plaintext, kept only so a user can tell two
            // keys apart in the list. Not usable on its own.
            $table->string('token_prefix', 12);

            // Space-separated ability strings, e.g. "members:read events:read".
            // Stored as text rather than JSON so a token row stays readable in
            // the database without decoding.
            $table->string('abilities')->default('read');

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();  // null = never expires
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['church_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
