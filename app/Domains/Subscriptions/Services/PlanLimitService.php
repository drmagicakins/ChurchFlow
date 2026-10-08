<?php

namespace App\Domains\Subscriptions\Services;

use App\Models\Church;

class PlanLimitService
{
    /**
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException with a
     *         message shaped exactly like §14's example, so a controller
     *         can surface it directly without reformatting.
     */
    public function assertCanAdd(Church $church, string $resource, int $currentCount): void
    {
        $limit = $this->limitFor($church, $resource);

        if ($limit === null) {
            return; // unlimited on this plan
        }

        if ($currentCount >= $limit) {
            $label = match ($resource) {
                'members' => 'members',
                'branches' => 'branches',
                'admins' => 'administrators',
                default => $resource,
            };

            abort(422, "You've reached the {$label} limit for your current plan. Current: {$currentCount} / {$limit} {$label}. Upgrade your plan to add more {$label}.");
        }
    }

    public function limitFor(Church $church, string $resource): ?int
    {
        $subscription = \App\Models\Subscription::withoutGlobalScopes()
            ->where('church_id', $church->id)
            ->latest()
            ->first();

        if (!$subscription) {
            return null; // no subscription resolved yet (e.g. mid-checkout) — nothing to enforce
        }

        $plan = $subscription->plan;

        return match ($resource) {
            'members' => $plan->max_members,
            'branches' => $plan->max_branches,
            'admins' => $plan->max_admins,
            default => null,
        };
    }
}
