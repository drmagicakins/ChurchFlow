<?php

namespace App\Mail;

use App\Models\SubventionSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubventionDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SubventionSubmission $submission,
        public readonly string $decision,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Subvention '.ucfirst($this->decision).': '.$this->submission->organizationalUnit->name)
            ->view('emails.subvention-decision');
    }
}
