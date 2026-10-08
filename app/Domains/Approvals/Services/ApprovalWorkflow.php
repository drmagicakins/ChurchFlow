<?php

namespace App\Domains\Approvals\Services;

use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The one approval engine reused across domains (§Laravel Architecture:
 * "Approvals — shared state-machine engine reused by Subvention/Finance/
 * Loans"). An approvable model needs only:
 *   - a church_id column
 *   - an approval_status column (values: pending|approved|rejected, plus
 *     whatever "doesn't need approval" value that domain uses, e.g.
 *     Transaction's 'not_required')
 * This is intentionally a single-step approval (submit → approve/reject).
 * Multi-level approval chains (§9's "approval levels") are a real Phase 5
 * need for Subvention — extend this with an `approval_steps` table and a
 * `current_step` column on Approval rather than duplicating the engine.
 */
class ApprovalWorkflow
{
    public function submit(Model $approvable, User $submitter): Approval
    {
        abort_if(empty($approvable->church_id), 500, 'Approvable models must carry a church_id.');

        return DB::transaction(function () use ($approvable, $submitter) {
            $approval = Approval::create([
                'church_id' => $approvable->church_id,
                'approvable_type' => $approvable->getMorphClass(),
                'approvable_id' => $approvable->getKey(),
                'status' => 'pending',
                'submitted_by' => $submitter->id,
                'submitted_at' => now(),
            ]);

            $approvable->forceFill(['approval_status' => 'pending'])->save();

            return $approval;
        });
    }

    public function approve(Approval $approval, User $approver, ?string $comments = null): Approval
    {
        abort_if($approval->status !== 'pending', 409, 'This item has already been decided.');

        return DB::transaction(function () use ($approval, $approver, $comments) {
            $approval->update([
                'status' => 'approved',
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'comments' => $comments,
            ]);

            $approval->approvable?->forceFill(['approval_status' => 'approved'])->save();

            return $approval;
        });
    }

    public function reject(Approval $approval, User $approver, string $comments): Approval
    {
        abort_if($approval->status !== 'pending', 409, 'This item has already been decided.');

        return DB::transaction(function () use ($approval, $approver, $comments) {
            $approval->update([
                'status' => 'rejected',
                'decided_by' => $approver->id,
                'decided_at' => now(),
                'comments' => $comments,
            ]);

            $approval->approvable?->forceFill(['approval_status' => 'rejected'])->save();

            return $approval;
        });
    }
}
