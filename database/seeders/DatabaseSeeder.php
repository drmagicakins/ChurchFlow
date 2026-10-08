<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Phase 1 foundation data: the fixed permission catalog plus the
        // system-default "Church Owner" role (church_id = null) that is
        // cloned into each new church on registration.
        $this->call(RolePermissionSeeder::class);

        // Phase 8: the subscription plans a visitor chooses between. Seeded
        // here so a fresh clone has a working /plans page and a checkout it
        // can actually complete — placeholder prices, see PlanSeeder.
        $this->call(PlanSeeder::class);
    }
}
