<?php

namespace App\Domains\Security\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Time-based one-time passwords (RFC 6238) — the standard behind every
 * authenticator app (Google Authenticator, Authy, 1Password, Microsoft).
 *
 * WHY THIS IS WRITTEN HERE INSTEAD OF PULLED IN.
 * Two-factor is one HMAC and a MOD — roughly forty lines — while every package
 * that offers it also brings a QR-image renderer and an optional dependency
 * tree. Adding a dependency to the authentication path is a supply-chain
 * decision, not a convenience one, so this file implements only the maths and
 * leaves the QR to the `otpauth://` URI (which every authenticator app can
 * consume, and which a person can type by hand if they prefer).
 *
 * PARAMETERS. SHA-1, 6 digits, 30-second period and Base32 secrets are not
 * arbitrary: they are the defaults every authenticator app assumes when it is
 * given a bare secret in an otpauth URI. Deviating in one of them silently
 * breaks clients that do not read the parameters, so they are fixed here.
 *
 * THE WINDOW. One step either side (±30s) is accepted, which is what tolerates
 * a phone whose clock is slightly off. Widening it further would multiply the
 * number of valid codes at any instant, so it is not configurable.
 */
class TwoFactorService
{
    private const DIGITS = 6;

    private const PERIOD = 30;

    private const WINDOW = 1;

    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A fresh, cryptographically random Base32 secret (160 bits, as RFC 4226 §4 recommends). */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /** The `otpauth://` URI an authenticator app imports (its QR encodes this). */
    public function provisioningUri(string $secret, string $accountName, string $issuer = 'ChurchFlow'): string
    {
        $label = rawurlencode($issuer.':'.$accountName);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
    }

    /** The code that is valid RIGHT NOW — used by the provisioning screen's examples and by tests. */
    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        return $this->codeForStep($secret, intdiv($timestamp ?? time(), self::PERIOD));
    }

    /**
     * Does `$code` match the code for now, or one step either side?
     *
     * Comparison is constant-time so a wrong guess cannot be narrowed down by
     * response timing.
     */
    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return false;
        }

        $step = intdiv($timestamp ?? time(), self::PERIOD);

        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            if (hash_equals($this->codeForStep($secret, $step + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ten single-use recovery codes, for the phone that is lost or wiped.
     *
     * Returned in plain text exactly once (to be shown to the person), stored
     * HASHED. Recovery codes are a password, so they get the same treatment.
     */
    public function generateRecoveryCodes(int $count = 10): array
    {
        return Collection::times($count, fn () => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)))
            ->all();
    }

    public function hashRecoveryCode(string $code): string
    {
        return hash('sha256', $this->normaliseRecoveryCode($code));
    }

    /**
     * Match a submitted recovery code against the stored hashes and return the
     * stored list WITHOUT the consumed entry, so a code cannot be replayed.
     * Returns null when the code matches nothing.
     */
    public function consumeRecoveryCode(array $hashes, string $submitted): ?array
    {
        $want = $this->hashRecoveryCode($submitted);

        foreach ($hashes as $index => $hash) {
            if (hash_equals((string) $hash, $want)) {
                unset($hashes[$index]);

                return array_values($hashes);
            }
        }

        return null;
    }

    /** Recovery codes are typed by hand: accept either case and ignore spaces/dashes. */
    private function normaliseRecoveryCode(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    // --- the TOTP maths ----------------------------------------------------

    private function codeForStep(string $secret, int $step): string
    {
        $key = $this->base32Decode($secret);

        // 8-byte big-endian counter (RFC 4226 §5.2).
        $counter = pack('N*', 0).pack('N*', $step);

        $hash = hash_hmac('sha1', $counter, $key, true);

        // Dynamic truncation: the low nibble of the last byte picks the offset.
        $offset = ord(substr($hash, -1)) & 0x0F;

        $binary = substr($hash, $offset, 4);

        $value = unpack('N', $binary)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = Str::upper(preg_replace('/[^A-Za-z2-7]/', '', $encoded) ?? '');

        $bits = '';
        foreach (str_split($encoded) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::BASE32_ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }

    // --- user-facing helpers ----------------------------------------------

    /** Store a new (unconfirmed) secret on the user. */
    public function beginSetup(User $user): string
    {
        $secret = $this->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secret;
    }

    /** Mark the setup complete and return the plain-text recovery codes (shown once). */
    public function confirm(User $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => json_encode(array_map(fn ($c) => $this->hashRecoveryCode($c), $codes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $codes;
    }

    /** Turn it off and forget everything about it. */
    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function storedRecoveryHashes(User $user): array
    {
        return json_decode((string) $user->two_factor_recovery_codes, true) ?: [];
    }
}
