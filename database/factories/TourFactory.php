<?php

namespace Database\Factories;

use App\Models\Tour;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TourFactory extends Factory
{
    protected $model = Tour::class;

    public function definition(): array
    {
        return [
            'slug' => 'tour-'.Str::random(8),
            'name' => $this->faker->sentence(3),
            'version' => 1,
            'is_active' => true,
        ];
    }
}
