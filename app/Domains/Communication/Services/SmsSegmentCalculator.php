<?php

namespace App\Domains\Communication\Services;

/**
 * §21: a church must see exactly how many SMS units a message will
 * actually consume before sending, including multi-segment messages.
 * Standard GSM-7 vs. UCS-2 segmentation rules (the same ones virtually
 * every SMS gateway bills against):
 *   - GSM-7 (only characters in the GSM 03.38 basic set): 160 chars for a
 *     single segment, 153 chars per segment once concatenated (multipart
 *     messages reserve a few characters per segment for the UDH header).
 *   - UCS-2 (any character outside the GSM-7 set, e.g. most emoji or
 *     non-Latin scripts): 70 chars single segment, 67 per segment
 *     concatenated.
 * This is provider-agnostic on purpose — see SmsProviderInterface; a real
 * provider's exact billing may differ slightly and should be reconciled
 * against actual invoices, but this gives an honest, non-hand-wavy
 * estimate rather than a flat "1 unit per message" placeholder.
 */
class SmsSegmentCalculator
{
    private const GSM7_SINGLE = 160;
    private const GSM7_MULTI = 153;
    private const UCS2_SINGLE = 70;
    private const UCS2_MULTI = 67;

    // Not exhaustive (the full GSM 03.38 basic + extension tables run to
    // ~128 characters) but covers the common case; extend as needed.
    private const GSM7_PATTERN = '/^[@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,\-.\/0-9:;<=>?¡A-ZÄÖÑÜ§¿a-zäöñüà^{}\\\\\[~\]|€]*$/u';

    public function segmentsFor(string $message): int
    {
        $length = mb_strlen($message);

        if ($length === 0) {
            return 0;
        }

        $isGsm7 = (bool) preg_match(self::GSM7_PATTERN, $message);

        [$single, $multi] = $isGsm7
            ? [self::GSM7_SINGLE, self::GSM7_MULTI]
            : [self::UCS2_SINGLE, self::UCS2_MULTI];

        if ($length <= $single) {
            return 1;
        }

        return (int) ceil($length / $multi);
    }
}
