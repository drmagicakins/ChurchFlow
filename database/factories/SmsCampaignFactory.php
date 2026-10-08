<?php

namespace Database\Factories;

use App\Models\Church;
use App\Models\SmsCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmsCampaignFactory extends Factory
{
    protected $model = SmsCampaign::class;

    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'name' => $this->faker->sentence(3),
            'message' => 'Service starts at 9am this Sunday. See you there!',
            'audience_type' => 'church',
            'status' => 'draft',
        ];
    }
}
