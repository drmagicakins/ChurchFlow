<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'created_by' => User::factory(),
            'subject' => $this->faker->sentence(4),
            'category' => 'technical',
            'description' => $this->faker->paragraph(),
            'priority' => 'normal',
            'status' => 'open',
        ];
    }
}
