<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'title' => $this->faker->sentence(3),
            'starts_at' => now()->addWeek(),
            'status' => 'scheduled',
        ];
    }
}
