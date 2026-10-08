<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\SubventionRuleSet;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubventionRuleSetFactory extends Factory
{
    protected $model = SubventionRuleSet::class;

    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'name' => 'Group '.$this->faker->unique()->numberBetween(1, 999),
        ];
    }
}
