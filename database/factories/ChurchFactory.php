<?php

namespace Database\Factories;

use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ChurchFactory extends Factory
{
    protected $model = Church::class;

    public function definition(): array
    {
        $name = $this->faker->company().' Church';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(6),
            'status' => 'active',
        ];
    }
}
