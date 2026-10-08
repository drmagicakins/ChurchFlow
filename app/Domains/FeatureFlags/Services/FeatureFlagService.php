<?php

namespace App\Domains\FeatureFlags\Services;

use App\Models\Church;
use App\Models\FeatureFlag;
use App\Models\FeatureFlagChurch;

class FeatureFlagService
{
    /**
     * An unknown flag key defaults to OFF (fail closed) rather than
     * throwing — a typo'd or not-yet-seeded flag key should quietly hide a
     * feature, never 500 the page it gates.
     */
    public function isEnabled(string $key, ?Church $church = null): bool
    {
        $flag = FeatureFlag::where('key', $key)->first();

        if (!$flag) {
            return false;
        }

        if ($church) {
            $override = FeatureFlagChurch::where('feature_flag_id', $flag->id)
                ->where('church_id', $church->id)
                ->first();

            if ($override) {
                return $override->is_enabled;
            }
        }

        return $flag->is_globally_enabled;
    }

    public function setGlobal(string $key, bool $enabled, string $label, ?string $description = null): FeatureFlag
    {
        return FeatureFlag::updateOrCreate(
            ['key' => $key],
            ['label' => $label, 'description' => $description, 'is_globally_enabled' => $enabled],
        );
    }

    /** §56: a gradual rollout — enable (or explicitly disable) a flag for one church regardless of the global value. */
    public function setForChurch(string $key, Church $church, bool $enabled): void
    {
        $flag = FeatureFlag::where('key', $key)->firstOrFail();

        FeatureFlagChurch::updateOrCreate(
            ['feature_flag_id' => $flag->id, 'church_id' => $church->id],
            ['is_enabled' => $enabled],
        );
    }

    public function clearChurchOverride(string $key, Church $church): void
    {
        $flag = FeatureFlag::where('key', $key)->first();

        if ($flag) {
            FeatureFlagChurch::where('feature_flag_id', $flag->id)->where('church_id', $church->id)->delete();
        }
    }
}
