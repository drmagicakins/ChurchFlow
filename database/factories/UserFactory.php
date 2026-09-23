<?php

namespace Database\Factories;

use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Every normal user belongs to a church; tests that need a
            // platform admin override this with ['church_id' => null].
            'church_id' => Church::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_platform_admin' => false,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A platform admin has no church context (see architecture doc §53 and
     * IdentifyTenant middleware).
     */
    public function platformAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'church_id' => null,
            'is_platform_admin' => true,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
