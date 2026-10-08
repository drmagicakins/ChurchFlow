<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * PLACEHOLDER pricing and limits — the brief explicitly says not to decide
 * final pricing ("₦XX,XXX"). These numbers exist only so the checkout flow
 * has something to render and charge in development; replace them through
 * the platform admin area (or this seeder) before launch.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'Starter', 'slug' => 'starter', 'monthly_price' => 10000, 'yearly_price' => 100000,
                'max_members' => 200, 'max_branches' => 1, 'max_admins' => 3, 'storage_mb' => 1024, 'sort_order' => 1,
                'features' => ['api_access' => false, 'custom_domain' => false, 'advanced_reports' => false]],
            ['name' => 'Growth', 'slug' => 'growth', 'monthly_price' => 25000, 'yearly_price' => 250000,
                'max_members' => 1000, 'max_branches' => 5, 'max_admins' => 10, 'storage_mb' => 10240, 'sort_order' => 2,
                'features' => ['api_access' => false, 'custom_domain' => false, 'advanced_reports' => true]],
            ['name' => 'Professional', 'slug' => 'professional', 'monthly_price' => 60000, 'yearly_price' => 600000,
                'max_members' => null, 'max_branches' => null, 'max_admins' => null, 'storage_mb' => 51200, 'sort_order' => 3,
                'features' => ['api_access' => true, 'custom_domain' => true, 'advanced_reports' => true]],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
