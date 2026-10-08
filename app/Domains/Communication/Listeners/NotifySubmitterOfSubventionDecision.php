<?php

namespace App\Domains\Communication\Listeners;

use App\Domains\Communication\Services\NotificationService;
use App\Domains\Subventions\Events\SubventionSubmissionDecided;
use App\Mail\SubventionDecisionMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * §Email is included/free with the subscription — this listener sends
 * both an in-app notification (always) and an email (best-effort; a mail
 * failure here must never break the approval transaction that already
 * committed, which is exactly why this runs as a queued listener rather
 * than inline inside SubventionWorkflowService).
 */
class NotifySubmitterOfSubventionDecision implements ShouldQueue
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(SubventionSubmissionDecided $event): void
    {
        $submission = $event->submission;
        $submitter = $submission->submitter;

        if (!$submitter) {
            return;
        }

        $branchName = $submission->organizationalUnit->name;
        $periodName = $submission->period->name;

        $title = match ($event->decision) {
            'approved' => "Subvention approved: {$branchName} — {$periodName}",
            'rejected' => "Subvention rejected: {$branchName} — {$periodName}",
            'returned' => "Subvention returned for revision: {$branchName} — {$periodName}",
            default => "Subvention update: {$branchName} — {$periodName}",
        };

        $this->notifications->send(
            $submitter,
            'subvention.'.$event->decision,
            $title,
            $submission->return_reason,
        );

        if ($submitter->email) {
            Mail::to($submitter->email)->queue(new SubventionDecisionMail($submission, $event->decision));
        }
    }
}
