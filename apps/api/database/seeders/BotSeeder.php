<?php

namespace Database\Seeders;

use App\Enums\BotDifficulty;
use App\Models\BotProfile;
use App\Models\Game;
use Illuminate\Database\Seeder;

/**
 * Sample bot opponents. Names are examples; administrators rename, add or disable bots.
 * Idempotent and non-destructive.
 */
class BotSeeder extends Seeder
{
    /** @var array<string, BotDifficulty> */
    private const BOTS = [
        'Alex' => BotDifficulty::Easy,
        'David' => BotDifficulty::Easy,
        'Michael' => BotDifficulty::Medium,
        'Daniel' => BotDifficulty::Medium,
        'Aram' => BotDifficulty::Medium,
        'Robert' => BotDifficulty::Hard,
        'Levon' => BotDifficulty::Hard,
    ];

    public function run(): void
    {
        $games = Game::query()->pluck('id');

        foreach (self::BOTS as $name => $difficulty) {
            $bot = BotProfile::query()->firstOrCreate(['name' => $name]);

            if ($bot->wasRecentlyCreated) {
                foreach ($games as $gameId) {
                    $bot->gameProfiles()->create(['game_id' => $gameId, 'difficulty' => $difficulty]);
                }
            }
        }
    }
}
