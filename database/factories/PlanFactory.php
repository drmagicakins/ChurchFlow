<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name).'-'.Str::random(4),
            'monthly_price' => 10000,
            'yearly_price' => 100000,
            'currency' => 'NGN',
            'max_members' => null,
            'max_branches' => null,
            'max_admins' => null,
            'is_active' => true,
        ];
    }
}
