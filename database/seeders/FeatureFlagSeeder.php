<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        FeatureFlag::updateOrCreate(
            ['key' => 'finance_v2_enabled'],
            ['label' => 'Finance v2', 'description' => 'Next-generation finance module, in beta.', 'is_globally_enabled' => false],
        );

        FeatureFlag::updateOrCreate(
            ['key' => 'new_dashboard_enabled'],
            ['label' => 'New dashboard', 'description' => 'Redesigned role-specific dashboards.', 'is_globally_enabled' => false],
        );
    }
}
