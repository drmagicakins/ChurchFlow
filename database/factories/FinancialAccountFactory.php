<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\FinancialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinancialAccountFactory extends Factory
{
    protected $model = FinancialAccount::class;

    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'name' => $this->faker->unique()->word().' Fund',
            'type' => 'general',
        ];
    }
}
