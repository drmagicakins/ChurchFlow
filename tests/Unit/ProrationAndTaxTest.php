<?php

namespace Tests\Unit;

use App\Domains\PlatformAdmin\Services\PlatformSettingsService;
use App\Domains\Subscriptions\Services\ProrationCalculator;
use App\Domains\Subscriptions\Services\TaxCalculator;
use App\Models\Church;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProrationAndTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrading_halfway_through_a_30_day_period_charges_the_price_difference_for_the_remaining_days(): void
    {
        $church = Church::factory()->create();
        $oldPlan = Plan::factory()->create(['monthly_price' => 10000]);
        $newPlan = Plan::factory()->create(['monthly_price' => 25000]);

        $subscription = Subscription::create([
            'church_id' => $church->id, 'plan_id' => $oldPlan->id, 'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_start' => Carbon::parse('2026-01-01'),
            'current_period_end' => Carbon::parse('2026-01-31'), // 30 days
        ]);
        $subscription->setRelation('plan', $oldPlan);

        $proration = (new ProrationCalculator())->calculate($subscription, $newPlan, Carbon::parse('2026-01-16')); // 15 days remaining

        $this->assertSame(15, $proration['days_remaining']);
        // old daily rate = 10000/30 = 333.333333; credit for 15 days = 5000.00
        $this->assertSame('5000.00', $proration['credit']);
        // new daily rate = 25000/30 = 833.333333; charge for 15 days = 12500.00
        $this->assertSame('12500.00', $proration['charge']);
        $this->assertSame('7500.00', $proration['net']); // charge - credit, a positive amount owed now
    }

    public function test_downgrading_produces_a_negative_net_amount_a_credit(): void
    {
        $church = Church::factory()->create();
        $oldPlan = Plan::factory()->create(['monthly_price' => 25000]);
        $newPlan = Plan::factory()->create(['monthly_price' => 10000]);

        $subscription = Subscription::create([
            'church_id' => $church->id, 'plan_id' => $oldPlan->id, 'status' => 'active',
            'billing_interval' => 'monthly',
            'current_period_start' => Carbon::parse('2026-01-01'),
            'current_period_end' => Carbon::parse('2026-01-31'),
        ]);
        $subscription->setRelation('plan', $oldPlan);

        $proration = (new ProrationCalculator())->calculate($subscription, $newPlan, Carbon::parse('2026-01-16'));

        $this->assertTrue(bccomp($proration['net'], '0', 2) < 0, 'Downgrading mid-cycle should net to a credit, not a charge.');
    }

    public function test_zero_default_tax_rate_produces_no_tax(): void
    {
        $settings = new PlatformSettingsService();
        $result = (new TaxCalculator($settings))->calculate('1000.00');

        $this->assertSame('0.00', $result['tax_amount']);
    }

    public function test_a_configured_tax_rate_is_applied_correctly(): void
    {
        $settings = new PlatformSettingsService();
        $settings->set('default_tax_rate', '7.5');

        $result = (new TaxCalculator($settings))->calculate('1000.00');

        $this->assertSame('7.50', $result['rate']);
        $this->assertSame('75.00', $result['tax_amount']);
    }
}
