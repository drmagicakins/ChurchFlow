<?php

namespace App\Domains\Onboarding\Services;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Models\Church;
use App\Models\ChurchSetupProgress;
use App\Models\User;

/**
 * §33-34: "Let's get your church ready" — a progress checklist, not a
 * blocking wizard. Every step but the church profile itself is skippable
 * (§11: "Allow users to skip non-critical steps and complete them later"),
 * which is why this tracks completion per step rather than a single
 * all-or-nothing "onboarded" flag.
 */
class SetupWizardService
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    /** @return array<int, array{key:string, label:string, required:bool}> */
    public function steps(): array
    {
        return [
            ['key' => 'church_profile', 'label' => 'Church profile', 'required' => true],
            ['key' => 'structure', 'label' => 'Organizational structure', 'required' => false],
            ['key' => 'departments', 'label' => 'Departments', 'required' => false],
            ['key' => 'members', 'label' => 'Add your first members', 'required' => false],
            ['key' => 'finance', 'label' => 'Financial settings', 'required' => false],
            ['key' => 'invite_admins', 'label' => 'Invite administrators', 'required' => false],
        ];
    }

    /** @return array<int, array{key:string, label:string, required:bool, completed:bool}> */
    public function progressFor(Church $church): array
    {
        $completedKeys = ChurchSetupProgress::withoutGlobalScopes()
            ->where('church_id', $church->id)
            ->whereNotNull('completed_at')
            ->pluck('step_key')
            ->all();

        return array_map(
            fn (array $step) => [...$step, 'completed' => in_array($step['key'], $completedKeys, true)],
            $this->steps(),
        );
    }

    public function percentComplete(Church $church): int
    {
        $progress = $this->progressFor($church);
        $completed = count(array_filter($progress, fn ($s) => $s['completed']));

        return (int) round(($completed / max(count($progress), 1)) * 100);
    }

    public function isComplete(Church $church): bool
    {
        return $this->percentComplete($church) === 100;
    }

    public function markStepComplete(Church $church, string $stepKey, User $completedBy): void
    {
        abort_unless(
            in_array($stepKey, array_column($this->steps(), 'key'), true),
            422,
            'Unknown setup step.'
        );

        $wasComplete = $this->isComplete($church);

        ChurchSetupProgress::withoutGlobalScopes()->updateOrCreate(
            ['church_id' => $church->id, 'step_key' => $stepKey],
            ['completed_at' => now(), 'completed_by' => $completedBy->id],
        );

        $this->analytics->track('setup_step_completed', $completedBy, $church, ['step' => $stepKey]);

        if (!$wasComplete && $this->isComplete($church)) {
            $this->analytics->track('onboarding_completed', $completedBy, $church);
        }
    }

    public function skipStep(Church $church, string $stepKey, User $skippedBy): void
    {
        $this->analytics->track('setup_step_skipped', $skippedBy, $church, ['step' => $stepKey]);
    }
}
