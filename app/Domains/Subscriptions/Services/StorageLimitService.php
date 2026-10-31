<?php

namespace App\Domains\Subscriptions\Services;

use App\Models\Church;
use App\Models\UploadedFile;

/**
 * §14: `plans.storage_mb` has been in the schema (and seeded per tier) since
 * Phase 8, but nothing ever read it — so every church had unlimited storage
 * regardless of the plan it paid for. This is the enforcement point for that
 * last unenforced column.
 *
 * Deliberately mirrors PlanLimitService's contract:
 *   - `null` means UNLIMITED (a plan with no storage_mb set);
 *   - a numeric limit is compared against CURRENT usage plus the INCOMING file,
 *     not against usage alone, so the upload that would cross the line is the
 *     one that is refused rather than the one after it;
 *   - the failure is a 422 with the same "Current / limit … Upgrade your plan"
 *     shape PlanLimitService uses, so both limits read the same in the UI.
 *
 * Two decisions worth stating:
 *   1. Usage is the SUM of `uploaded_files.size_bytes` for the church — the only
 *      per-file size this application records. It does not stat the disk, so it
 *      cannot drift from what the app believes it stored.
 *   2. Being over quota RESTRICTS NEW UPLOADS ONLY. It never deletes or hides a
 *      file a church already uploaded — the same "restrict, never destroy" rule
 *      EnsureSubscriptionAllowsAccess applies on expiry (§35).
 */
class StorageLimitService
{
    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException when the
     *         incoming file would exceed the church's plan quota.
     */
    public function assertCanStore(Church $church, int $incomingBytes): void
    {
        $limitMb = $this->limitMbFor($church);

        if ($limitMb === null) {
            return; // unlimited on this plan
        }

        $limitBytes = $limitMb * 1024 * 1024;
        $usedBytes = $this->usedBytes($church);

        if ($usedBytes + $incomingBytes <= $limitBytes) {
            return;
        }

        abort(422, sprintf(
            "You've reached the storage limit for your current plan. Current: %s / %s. Upgrade your plan to upload more files.",
            $this->humanMb($usedBytes),
            $this->humanMb($limitBytes),
        ));
    }

    public function usedBytes(Church $church): int
    {
        // withoutGlobalScopes + an explicit church_id filter: this runs during an
        // upload where a tenant is normally bound, but it must also be correct
        // when called from a job or a platform-admin context where it is not.
        return (int) UploadedFile::withoutGlobalScopes()
            ->where('church_id', $church->id)
            ->sum('size_bytes');
    }

    public function limitMbFor(Church $church): ?int
    {
        $subscription = \App\Models\Subscription::withoutGlobalScopes()
            ->with('plan')
            ->where('church_id', $church->id)
            ->latest()
            ->first();

        if (! $subscription || ! $subscription->plan) {
            return null; // no subscription resolved yet (e.g. mid-checkout) — nothing to enforce
        }

        return $subscription->plan->storage_mb === null
            ? null
            : (int) $subscription->plan->storage_mb;
    }

    /** Human-readable size for the error message: 1.5 GB, 900 MB. */
    private function humanMb(int $bytes): string
    {
        $gb = $bytes / (1024 * 1024 * 1024);

        if ($gb >= 1) {
            return rtrim(rtrim(number_format($gb, 2), '0'), '.').' GB';
        }

        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1), '0'), '.').' MB';
    }
}
