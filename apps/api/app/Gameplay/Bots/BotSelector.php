<?php

namespace App\Gameplay\Bots;

use App\Enums\BotDifficulty;
use App\Models\BotProfile;
use App\Models\Game;
use InvalidArgumentException;

/**
 * Picks an enabled bot that plays the game at the requested difficulty.
 */
class BotSelector
{
    /**
     * @param  list<int>  $exclude  Bot ids already in the room.
     */
    public function pick(Game $game, BotDifficulty $difficulty, array $exclude = []): BotProfile
    {
        $bot = BotProfile::query()
            ->where('is_enabled', true)
            ->whereNotIn('id', $exclude)
            ->whereHas('gameProfiles', fn ($q) => $q->where('game_id', $game->id)->where('difficulty', $difficulty)->where('is_enabled', true))
            ->inRandomOrder()
            ->first();

        return $bot ?? throw new InvalidArgumentException('No bot is available for this game and difficulty.');
    }

    /**
     * Difficulties at least one enabled bot plays for the game.
     *
     * @return list<BotDifficulty>
     */
    public function difficulties(Game $game): array
    {
        return collect(BotDifficulty::cases())
            ->filter(fn (BotDifficulty $d) => BotProfile::query()->where('is_enabled', true)
                ->whereHas('gameProfiles', fn ($q) => $q->where('game_id', $game->id)->where('difficulty', $d)->where('is_enabled', true))
                ->exists())
            ->values()
            ->all();
    }
}
