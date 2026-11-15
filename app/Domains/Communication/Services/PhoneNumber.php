<?php

namespace App\Domains\Communication\Services;

/**
 * Decides what a stored phone number actually means before an SMS is sent.
 *
 * A number that looks fine on screen can be impossible to send to, and the
 * dangerous cases are silent rather than loud:
 *
 *  - A spreadsheet writes a long number in scientific notation: 2348012345678
 *    becomes `2.34801E+12`. The digits are GONE, not merely formatted, so no
 *    amount of tidying can recover them. Sending to it either fails at the
 *    gateway or, worse, goes to whatever `234801` turns out to be.
 *  - A number padded with spaces, dashes or brackets ("0803 123 4567") is
 *    perfectly readable to a person and unparseable to a provider.
 *  - A number that never had a country code needs one, and guessing wrong
 *    sends a Nigerian church's reminder abroad.
 *
 * This class therefore answers one question — "can this be sent, and as what?"
 * — so every SMS path agrees, instead of each screen deciding for itself.
 * Nothing here ever REWRITES stored data: it only reports what can be sent.
 */
class PhoneNumber
{
    /** Default dialling code, used only when a local-format number needs one. */
    public const DEFAULT_COUNTRY_CODE = '234';

    /**
     * Normalise to digits (optionally with a leading +) and return it, or null
     * when the value cannot be a real phone number.
     *
     * A null answer means "do not send": the caller must skip the person rather
     * than hand the provider something it will charge for and fail on.
     */
    public function normalise(mixed $raw, ?string $countryCode = null): ?string
    {
        if ($raw === null) {
            return null;
        }

        // Floats and float-like strings are the spreadsheet damage described in
        // the class docblock. 2.34801E+12 has lost every digit after the fifth,
        // so it is refused outright — this is the one case that must never be
        // "cleaned", because cleaning it would invent a number.
        if (is_float($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        if ($value === '' || preg_match('/^[0-9.]+e\+?[0-9]+$/i', $value)) {
            return null;
        }

        // Keep a leading + (international), drop everything else that is not a
        // digit: spaces, dashes, dots, brackets, "tel:" prefixes.
        $hasPlus = str_starts_with($value, '+');
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        // A leading 00 is the other way of writing "+" internationally.
        if (! $hasPlus && str_starts_with($digits, '00') && strlen($digits) >= 11) {
            $hasPlus = true;
            $digits = substr($digits, 2);
        }

        if ($hasPlus) {
            return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$digits : null;
        }

        // Local format (0803 123 4567): add the country code and drop the trunk 0.
        $cc = ltrim((string) ($countryCode ?: self::DEFAULT_COUNTRY_CODE), '+');

        if (str_starts_with($digits, '0')) {
            $digits = $cc.substr($digits, 1);
        } elseif (strlen($digits) <= 10) {
            $digits = $cc.$digits;
        }

        // E.164 allows up to 15 digits; anything shorter than 8 is not a phone.
        return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$digits : null;
    }

    /** Can this value be sent to at all? The question the screens ask. */
    public function isSendable(mixed $raw, ?string $countryCode = null): bool
    {
        return $this->normalise($raw, $countryCode) !== null;
    }

    /**
     * Why a number cannot be sent, in words for a person — or null when it can.
     *
     * Used to explain a skipped recipient instead of leaving a church owner
     * wondering why "10 registered, 6 sent" with four unexplained names.
     */
    public function problemWith(mixed $raw): ?string
    {
        if ($raw === null || trim((string) $raw) === '') {
            return 'no phone number';
        }

        if (is_float($raw) || preg_match('/^[0-9.]+e\+?[0-9]+$/i', trim((string) $raw))) {
            return 'the number was saved in scientific notation (e.g. 2.34801E+12) and its digits are lost';
        }

        return $this->normalise($raw) === null ? 'not a recognisable phone number' : null;
    }
}