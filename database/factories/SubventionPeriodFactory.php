<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\SubventionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubventionPeriodFactory extends Factory
{
    protected $model = SubventionPeriod::class;

    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'name' => $this->faker->unique()->monthName().' '.now()->year,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
        ];
    }
}
