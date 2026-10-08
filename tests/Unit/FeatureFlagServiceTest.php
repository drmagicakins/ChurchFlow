<?php

namespace Tests\Unit;

use App\Domains\FeatureFlags\Services\FeatureFlagService;
use App\Models\Church;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_flag_key_defaults_to_disabled_rather_than_erroring(): void
    {
        $this->assertFalse((new FeatureFlagService())->isEnabled('does_not_exist'));
    }

    public function test_global_value_applies_when_no_church_override_exists(): void
    {
        $service = new FeatureFlagService();
        $service->setGlobal('new_dashboard_enabled', true, 'New dashboard');

        $church = Church::factory()->create();

        $this->assertTrue($service->isEnabled('new_dashboard_enabled', $church));
        $this->assertTrue($service->isEnabled('new_dashboard_enabled')); // no church context at all
    }

    public function test_a_church_override_wins_over_the_global_value_in_both_directions(): void
    {
        $service = new FeatureFlagService();
        $service->setGlobal('finance_v2_enabled', false, 'Finance v2');

        $churchA = Church::factory()->create();
        $churchB = Church::factory()->create();

        $service->setForChurch('finance_v2_enabled', $churchA, true); // opted in early, global still off
        $this->assertTrue($service->isEnabled('finance_v2_enabled', $churchA));
        $this->assertFalse($service->isEnabled('finance_v2_enabled', $churchB));

        $service->setGlobal('finance_v2_enabled', true, 'Finance v2'); // now rolled out globally
        $service->setForChurch('finance_v2_enabled', $churchB, false); // but explicitly opted out
        $this->assertTrue($service->isEnabled('finance_v2_enabled', $churchA));
        $this->assertFalse($service->isEnabled('finance_v2_enabled', $churchB));
    }

    public function test_clearing_a_church_override_falls_back_to_the_global_value(): void
    {
        $service = new FeatureFlagService();
        $service->setGlobal('new_dashboard_enabled', false, 'New dashboard');
        $church = Church::factory()->create();
        $service->setForChurch('new_dashboard_enabled', $church, true);

        $this->assertTrue($service->isEnabled('new_dashboard_enabled', $church));

        $service->clearChurchOverride('new_dashboard_enabled', $church);

        $this->assertFalse($service->isEnabled('new_dashboard_enabled', $church));
    }
}
