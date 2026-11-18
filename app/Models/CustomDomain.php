<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * §14: a hostname a church has claimed for its workspace.
 *
 * The record is deliberately not "trusted on create". A church proves it
 * controls the hostname by publishing `verification_token` as a DNS TXT record;
 * only then does the domain become 'verified'. That is what stops one church
 * from pointing a hostname it does not own at the platform — the same DNS-based
 * proof every custom-domain provider uses, and the reason `status` exists
 * instead of a bare `hostname` column.
 */
class CustomDomain extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'church_id', 'hostname', 'status', 'verification_token',
        'verified_at', 'last_checked_at', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * The TXT record host a church must create.
     *
     * Always the apex-style `_churchflow.<hostname>`, so the instruction is a
     * single deterministic string rather than something the UI constructs
     * differently from the checker.
     */
    public function verificationRecordName(): string
    {
        return '_churchflow.'.$this->hostname;
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Normalise what a person types into a bare hostname: strip a scheme, a
     * path, a port, surrounding whitespace, and a trailing dot. "https://My
     * Church.org/" and "my church.org" should not be two different domains,
     * and neither should be rejected for lacking (or having) an "https://".
     */
    public static function normalise(string $input): string
    {
        $host = trim(Str::lower($input));
        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host) ?? $host; // scheme
        $host = preg_split('#[/?\#]#', $host)[0] ?? $host;                   // path/query
        $host = preg_replace('#:\d+$#', '', $host) ?? $host;                 // port
        $host = rtrim($host, '.');

        return $host;
    }

    /** A fresh token for a newly claimed hostname. */
    public static function newVerificationToken(): string
    {
        return 'cf-verify-'.Str::random(32);
    }
}
