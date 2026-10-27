<?php

namespace Database\Factories;

use App\Models\BotProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BotProfile>
 */
class BotProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->firstName(),
            'is_enabled' => true,
        ];
    }
}
