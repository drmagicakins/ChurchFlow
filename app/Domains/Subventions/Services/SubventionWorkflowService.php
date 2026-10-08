<?php

namespace App\Domains\Subventions\Services;

use App\Domains\Approvals\Services\ApprovalWorkflow;
use App\Domains\Loans\Services\LoanService;
use App\Domains\Subventions\Events\SubventionSubmissionDecided;
use App\Models\SubventionSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * §17: Draft → Submitted → Under Review → Returned → Approved → Rejected.
 *
 * The final decision (Approved/Rejected) is delegated entirely to the
 * shared ApprovalWorkflow (Domains/Approvals) built for Finance in Phase 4
 * — this class does not re-implement "pending vs. decided" itself. What it
 * adds on top is domain-specific: calculating before submission, the
 * softer "Returned" outcome (which closes out the current approval as
 * rejected but leaves the submission resubmittable, unlike a hard
 * Rejected), and — the one piece of real money movement in this whole
 * module — creating an actual LoanPayment only once a submission is
 * finally Approved.
 */
class SubventionWorkflowService
{
    public function __construct(
        private readonly SubventionCalculationEngine $engine,
        private readonly ApprovalWorkflow $approvals,
        private readonly LoanService $loans,
    ) {}

    public function submit(SubventionSubmission $submission, User $submitter): SubventionSubmission
    {
        abort_unless($submission->status === 'draft', 409, 'Only a draft submission can be submitted.');

        return DB::transaction(function () use ($submission, $submitter) {
            $this->engine->calculate($submission);

            $submission->forceFill([
                'status' => 'submitted',
                'submitted_by' => $submitter->id,
                'submitted_at' => now(),
            ])->save();

            $this->approvals->submit($submission, $submitter);

            return $submission->fresh();
        });
    }

    public function markUnderReview(SubventionSubmission $submission, User $reviewer): SubventionSubmission
    {
        abort_unless($submission->status === 'submitted', 409, 'Only a submitted item can move to review.');

        $submission->update(['status' => 'under_review', 'reviewed_by' => $reviewer->id]);

        return $submission;
    }

    /**
     * A soft, resubmittable outcome — distinct from reject(). The current
     * Approval is closed out as 'rejected' (it's a terminal decision on
     * THAT approval record) but the submission itself goes back to 'draft'
     * so the branch can correct its figures and submit() again, which
     * creates a brand-new Approval. Nothing about a past, closed Approval
     * is ever reopened or edited.
     */
    public function returnForRevision(SubventionSubmission $submission, User $reviewer, string $reason): SubventionSubmission
    {
        abort_unless(
            in_array($submission->status, ['submitted', 'under_review'], true),
            409,
            'Only a submitted or under-review item can be returned.'
        );

        return DB::transaction(function () use ($submission, $reviewer, $reason) {
            if ($submission->approval && $submission->approval->status === 'pending') {
                $this->approvals->reject($submission->approval, $reviewer, "Returned for revision: {$reason}");
            }

            $submission->update([
                'status' => 'returned',
                'return_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            SubventionSubmissionDecided::dispatch($submission->fresh(), 'returned');

            return $submission;
        });
    }

    /** Puts a returned submission back into draft so it can be edited and resubmitted. */
    public function reopen(SubventionSubmission $submission): SubventionSubmission
    {
        abort_unless($submission->status === 'returned', 409, 'Only a returned submission can be reopened.');

        $submission->update(['status' => 'draft', 'approval_status' => 'not_required']);

        return $submission;
    }

    /**
     * The only place in the entire Subvention module that touches real
     * money: once approved, and only once (ApprovalWorkflow::approve()
     * aborts on an already-decided approval, so this can't double-fire),
     * the calculation's loan_deduction_applied becomes an actual
     * LoanPayment ledger entry.
     */
    public function approve(SubventionSubmission $submission, User $approver, ?string $comments = null): SubventionSubmission
    {
        abort_unless($submission->approval, 409, 'This submission was never formally submitted for approval.');

        return DB::transaction(function () use ($submission, $approver, $comments) {
            $this->approvals->approve($submission->approval, $approver, $comments);

            $submission->update(['status' => 'approved', 'reviewed_by' => $approver->id, 'reviewed_at' => now()]);

            SubventionSubmissionDecided::dispatch($submission->fresh(), 'approved');

            $calculation = $submission->latestCalculation;

            if ($calculation && $calculation->loan_id && bccomp((string) $calculation->loan_deduction_applied, '0', 2) > 0) {
                $payment = $this->loans->recordPayment(
                    $calculation->loan,
                    (string) $calculation->loan_deduction_applied,
                    now()->toDateString(),
                    $approver,
                );
                $calculation->update(['loan_payment_id' => $payment->id]);
            }

            return $submission->fresh();
        });
    }

    public function reject(SubventionSubmission $submission, User $rejecter, string $reason): SubventionSubmission
    {
        abort_unless($submission->approval, 409, 'This submission was never formally submitted for approval.');

        return DB::transaction(function () use ($submission, $rejecter, $reason) {
            $this->approvals->reject($submission->approval, $rejecter, $reason);

            $submission->update(['status' => 'rejected', 'reviewed_by' => $rejecter->id, 'reviewed_at' => now()]);

            SubventionSubmissionDecided::dispatch($submission->fresh(), 'rejected');

            return $submission;
        });
    }
}
