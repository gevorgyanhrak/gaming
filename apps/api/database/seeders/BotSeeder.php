<?php

namespace Database\Seeders;

use App\Enums\BotDifficulty;
use App\Models\BotGameProfile;
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
    /** Five per difficulty: enough for a full six-seat table without two bots sharing a name. */
    private const BOTS = [
        'Alex' => BotDifficulty::Easy,
        'David' => BotDifficulty::Easy,
        'Anna' => BotDifficulty::Easy,
        'Mariam' => BotDifficulty::Easy,
        'Igor' => BotDifficulty::Easy,
        'Michael' => BotDifficulty::Medium,
        'Daniel' => BotDifficulty::Medium,
        'Aram' => BotDifficulty::Medium,
        'Nina' => BotDifficulty::Medium,
        'Sergey' => BotDifficulty::Medium,
        'Robert' => BotDifficulty::Hard,
        'Levon' => BotDifficulty::Hard,
        'Elena' => BotDifficulty::Hard,
        'Gor' => BotDifficulty::Hard,
        'Viktor' => BotDifficulty::Hard,
    ];

    public function run(): void
    {
        $games = Game::query()->pluck('id');
        // Games no bot plays yet (added since the bots were seeded): the sample bots learn them too.
        // Games with bots keep whatever the administrators set up.
        $newGames = Game::query()->whereNotIn('id', BotGameProfile::query()->select('game_id'))->pluck('id');

        $index = 0;
        foreach (self::BOTS as $name => $difficulty) {
            // Distinct, fairly high levels (18–57; 37 and 40 are coprime), the same as the migration's.
            $level = 18 + (++$index * 37) % 40;
            $bot = BotProfile::query()->firstOrCreate(['name' => $name], ['level' => $level]);

            foreach ($bot->wasRecentlyCreated ? $games : $newGames as $gameId) {
                $bot->gameProfiles()->create(['game_id' => $gameId, 'difficulty' => $difficulty]);
            }
        }
    }
}
