<?php

namespace App\Domains\Communication\Services;

use App\Models\Church;
use App\Models\OrganizationalUnit;

/**
 * WHOSE NAME a church's text is sent under (2026-11-17).
 *
 * The requirement was explicit: the sender id is "the name of the church of the
 * church owner, or the name of the parish pastor sending the SMS". Doing that
 * literally is not possible on an SMS gateway — `from` is a single alphanumeric
 * HEADER registered with the provider, not a free-text person's name, and Termii
 * rejects any `from` that is not one of the account's approved sender ids. So
 * this class answers the question the gateway can actually accept:
 *
 *     the CHURCH's name, and the PARISH's name when the message belongs to one.
 *
 * Resolution order, most specific first:
 *
 *   1. an explicit name passed by the caller (an override, when one exists);
 *   2. on a parish-scoped message, "PARISH · CHURCH" shortened to Termii's limit
 *      — so a pastor's congregation sees the parish, and the church is not lost;
 *   3. the church's own name;
 *   4. null → the caller passes null to the provider, which falls back to
 *      TERMII_SENDER_ID (the platform's approved sender).
 *
 * WHY NOT THE PASTOR'S PERSONAL NAME. Two reasons, and the second is decisive:
 * a campaign is sent by a church, not by a private individual, and a text
 * arriving from an unrecognised personal name is more likely to read as spam —
 * while Termii would simply refuse it. A parish-level header keeps the intent
 * (the recipient knows which congregation it is) and can actually be approved.
 *
 * Nothing here writes to the database: it only derives the string.
 */
class SmsSenderName
{
    /**
     * WHAT TERMII ACTUALLY ALLOWS, MEASURED (2026-11-17).
     *
     * The account's `/api/sender-id` list shows ids of 6–11 ALPHANUMERIC
     * characters (`Gifogs`, `MARTCODELTD`, `ABHSALUMNI`), so the ceiling is 11
     * and it is counted on letters and digits: spaces, "·" and hyphens do not
     * count towards it. "Lekki · Grace Chapel" therefore fits at 10.
     */
    public const MAX_LENGTH = 11;

    /**
     * Whether a DERIVED name (a church's or a parish's) may be used as the
     * sender at all.
     *
     * FALSE BY DEFAULT, deliberately. Termii only accepts a `from` that is an
     * APPROVED sender id on the account, and an arbitrary church name is not one
     * — so sending under it would mean every text is refused, after the church's
     * units were reserved (they are refunded, but the send is still lost).
     *
     * Until per-church headers are approved, messages go out under the account's
     * approved header (terii.sh `ChurchFlow`, pending approval at the time of
     * writing). Flip this to true ONLY for a deployment whose churches each hold
     * an approved header of their own:
     *
     *   SMS_SENDER_FROM_CHURCH_NAME=true
     */
    public function __construct(
        private readonly bool $useChurchName = false,
    ) {}

    /**
     * @param  string|null  $override  A name the caller has already chosen.
     *                                 Always used when it passes cleaning: an
     *                                 explicit choice is the operator's.
     */
    public function for(?Church $church = null, ?OrganizationalUnit $parish = null, ?string $override = null): ?string
    {
        $explicit = $this->clean($override);

        if ($explicit !== null) {
            return $explicit;
        }

        // Not approved for derived headers: return null so the provider uses its
        // own approved sender id (TERMII_SENDER_ID) rather than a name Termii
        // will refuse.
        if (! $this->useChurchName) {
            return null;
        }

        $churchName = $this->clean($church?->name);
        $parishName = $this->clean($parish?->name);

        if ($parishName !== null && $churchName !== null) {
            // Parish first, church second: the recipient belongs to the parish.
            $combined = $this->clean($parishName.' · '.$churchName);

            if ($combined !== null) {
                return $combined;
            }
        }

        return $parishName ?? $churchName;
    }

    /**
     * A sender id Termii will accept: one line of visible text, at most
     * MAX_LENGTH characters, no runs of whitespace, no control characters.
     *
     * A name longer than the limit is shortened at a WORD boundary where one
     * exists, so "Grace Chapel International" becomes "Grace" rather than
     * "Grace Chape". Nothing is invented to pad it.
     */
    public function clean(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        // Control characters (a pasted newline or tab) would be rejected by the
        // provider as an unknown sender, with an error blaming the configuration.
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return null;
        }

        if ($this->alphanumericLength($name) <= self::MAX_LENGTH) {
            return $name;
        }

        // The cap is on LETTERS AND DIGITS, so shorten until that count fits.
        // A cut that lands mid-word is then walked back to the last space before
        // it ("Lekki · Grace Chapel" → "Lekki · Grace", never "Lekki · Grace C"),
        // as long as the result still carries at least four letters. A name that
        // already fits is never cut.
        while ($name !== '' && $this->alphanumericLength($name) > self::MAX_LENGTH) {
            $name = mb_substr($name, 0, -1);
        }

        $lastSpace = mb_strrpos($name, ' ');

        if ($lastSpace !== false) {
            $head = rtrim(mb_substr($name, 0, $lastSpace));

            if ($head !== '' && $this->alphanumericLength($head) >= 4) {
                $name = $head;
            }
        }

        $name = trim((string) preg_replace('/\s*(·|-|,)\s*$/u', '', $name));

        return $name !== '' ? $name : null;
    }

    /** Letters and digits only — what a sender id's length is actually measured on. */
    private function alphanumericLength(string $value): int
    {
        return (int) preg_match_all('/[\p{L}\p{N}]/u', $value);
    }
}
