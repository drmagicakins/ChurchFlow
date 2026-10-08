<?php

namespace App\Domains\Subventions\Events;

use App\Models\SubventionSubmission;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by SubventionWorkflowService on approve()/reject()/returnForRevision().
 * Communication (this phase) listens for it to notify the submitter —
 * Finance/Subvention itself has no idea Communication exists, keeping the
 * domains decoupled the way the architecture doc's Events/Listeners
 * section intends.
 */
class SubventionSubmissionDecided
{
    use Dispatchable;

    public function __construct(
        public readonly SubventionSubmission $submission,
        public readonly string $decision, // approved|rejected|returned
    ) {}
}
