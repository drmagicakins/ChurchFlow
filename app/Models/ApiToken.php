<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * §46: a church-issued API key.
 *
 * Only the SHA-256 hash of the plaintext is stored. The plaintext is returned
 * exactly once, at creation, and is unrecoverable afterwards — regenerating is
 * the only recovery path. That is the same tradeoff a password makes, and it
 * is the correct one: an API key is a long-lived credential that a database
 * leak must not be able to turn into working access.
 */
class ApiToken extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'church_id', 'created_by', 'name', 'token_hash', 'token_prefix',
        'abilities', 'last_used_at', 'expires_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected $hidden = ['token_hash'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Mints a new token row. Returns [ApiToken $token, string $plaintext] — the
     * caller must surface $plaintext to the user immediately and never store it.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Church $church, ?User $creator, string $name, array $abilities = ['read'], ?int $expiresInDays = null): array
    {
        // A visible, unambiguous prefix plus 40 random bytes. The 'cf_' marker
        // makes a leaked key recognisable in a log or a support ticket, so it
        // can be revoked quickly rather than argued about.
        $plaintext = 'cf_'.Str::random(40);

        $token = static::create([
            'church_id' => $church->id,
            'created_by' => $creator?->id,
            'name' => $name,
            'token_hash' => hash('sha256', $plaintext),
            'token_prefix' => substr($plaintext, 0, 11),
            'abilities' => implode(' ', $abilities),
            'expires_at' => $expiresInDays !== null ? now()->addDays($expiresInDays) : null,
        ]);

        return [$token, $plaintext];
    }

    /** Resolve a plaintext token to a live (unrevoked, unexpired) row, or null. */
    public static function resolve(string $plaintext): ?self
    {
        $token = static::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $plaintext))
            ->first();

        if (! $token || ! $token->isActive()) {
            return null;
        }

        return $token;
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = preg_split('/\s+/', (string) $this->abilities, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // 'read' is the launch scope: every issued token may read. A token can
        // additionally hold narrower named abilities (members:read, etc.), but
        // nothing today grants write — the public surface is read-only at launch
        // by design, so a stolen key cannot mutate a church's data.
        return in_array($ability, $abilities, true) || in_array('read', $abilities, true);
    }
}
