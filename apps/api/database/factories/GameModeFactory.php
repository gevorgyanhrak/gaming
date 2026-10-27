<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameMode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameMode>
 */
class GameModeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'slug' => fake()->unique()->slug(2),
            'name' => ['en' => ucfirst(fake()->word())],
            'min_players' => 2,
            'max_players' => 2,
            'config' => [],
        ];
    }
}
