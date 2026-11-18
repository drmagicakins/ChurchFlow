<?php

namespace App\Mail\Concerns;

use App\Domains\Communication\Services\ChurchReplyAddresses;
use App\Models\Church;

/**
 * "Every email a church sends carries the church" — logo, signature, and a
 * Reply-To that reaches the people who can answer it.
 *
 * WHY A TRAIT RATHER THAN A BASE CLASS. The three church mailables
 * (announcement, event reminder, subvention decision) already extend Laravel's
 * Mailable and each has its own constructor and data. A shared base class would
 * force them into one shape; a trait only adds the few behaviours they must all
 * have, and each keeps its own subject and view. It also means this cannot be
 * applied by accident: a new mailable opts in with one `use` statement.
 *
 * WHAT IT DOES, AND WHAT IT DELIBERATELY DOES NOT
 * -----------------------------------------------
 * It BRANDS an existing message: it does not replace its body, subject or
 * recipients. Unknown callers are unaffected — a church that is null, or has no
 * owner and no pastor, produces no Reply-To at all and the platform default is
 * left alone rather than an address being invented.
 *
 * The Reply-To is a LIST: the church owner and the parish pastor(s). Mail
 * clients honour several addresses, and a reply then reaches both the person
 * with authority and the person who writes to the congregation.
 */
trait BrandedAsChurch
{
    /** The church this message belongs to, resolved by the using mailable. */
    abstract protected function brandedChurch(): ?Church;

    /**
     * The parish (organizational unit) the message concerns, when it concerns
     * one — so a branch notice answers to that branch's pastor specifically.
     */
    protected function brandedParishId(): ?int
    {
        return null;
    }

    /**
     * Apply the church's identity to this message. Call from build().
     *
     * The view data is merged rather than replaced, so a mailable keeps every
     * variable its template already used.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function churchBranding(array $data = []): array
    {
        $church = $this->brandedChurch();

        if (! $church) {
            return $data;
        }

        $replyTo = $this->brandedReplyAddresses($church);

        if ($replyTo !== []) {
            // `replyTo()` sets the mailable's own Reply-To header, so the mail
            // that is actually sent carries it — not merely a value the template
            // could print. Multiple addresses are allowed and expected: the owner
            // AND the parish pastor.
            $this->replyTo($replyTo);
        }

        return array_merge([
            'church' => $church,
            'churchReplyTo' => $replyTo,
        ], $data);
    }

    /** @return array<int, string> */
    protected function brandedReplyAddresses(Church $church): array
    {
        return app(ChurchReplyAddresses::class)->forChurch($church, $this->brandedParishId());
    }
}
