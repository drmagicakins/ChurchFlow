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

        // A subvention belongs to a PARISH (`organizational_unit_id`), and a
        // parish can be removed after the submission was decided. Reading
        // `->name` straight off that relation then crashed the queued listener
        // with "Attempt to read property \"name\" on null" — the job failed and
        // was retried for ever, and the submitter was never told the decision.
        // Same guard as the submitter above: without both pieces of context
        // there is nothing meaningful to say, so the notification is skipped
        // rather than failed. Nothing is lost — the in-app record of the
        // decision lives on the submission itself.
        if (! $submission->organizationalUnit || ! $submission->period) {
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
