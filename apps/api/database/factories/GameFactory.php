<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word());

        return [
            'slug' => str($name)->slug()->toString(),
            'name' => ['en' => $name],
            'description' => ['en' => fake()->sentence()],
            'rules' => ['en' => fake()->paragraphs(2, true)],
            'min_players' => 2,
            'max_players' => 2,
            'is_enabled' => false,
            'is_visible' => true,
            'sort_order' => 0,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_visible' => false]);
    }

    public function playable(): static
    {
        return $this->state(['is_enabled' => true]);
    }
}
