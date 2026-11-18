<?php

namespace App\Mail;

use App\Mail\Concerns\BrandedAsChurch;
use App\Models\Church;
use App\Models\SubventionSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The decision on a parish's subvention submission, sent to whoever filed it.
 *
 * BRANDED AND ANSWERABLE. It renders in the church's email frame and its
 * Reply-To names the church owner — the person who decides subventions — so a
 * pastor with a question answers the decision-maker rather than the platform.
 */
class SubventionDecisionMail extends Mailable
{
    use BrandedAsChurch, Queueable, SerializesModels;

    public function __construct(
        public readonly SubventionSubmission $submission,
        public readonly string $decision,
    ) {}

    protected function brandedChurch(): ?Church
    {
        return $this->submission->church;
    }

    protected function brandedParishId(): ?int
    {
        // Debtors answer to the owner, not to their own parish: leaving this
        // null means the whole church's pastorate is on the reply list too.
        return null;
    }

    public function build(): self
    {
        // The subject names the PARISH this submission was filed against. A
        // parish can be removed after the decision, so the relation is read
        // null-safely here rather than crashing the queued mail (see
        // NotifySubmitterOfSubventionDecision for the same guard).
        $branch = $this->submission->organizationalUnit?->name ?? 'your parish';

        return $this
            ->subject('Subvention '.ucfirst($this->decision).': '.$branch)
            ->view('emails.subvention-decision', $this->churchBranding([
                'submission' => $this->submission,
                'decision' => $this->decision,
            ]));
    }
}
