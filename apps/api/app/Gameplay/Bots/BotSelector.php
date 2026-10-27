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
     * $count bots for one table, all different while there are enough; with fewer bots than seats the
     * same bots sit down more than once.
     *
     * @return list<BotProfile>
     *
     * @throws InvalidArgumentException when no bot plays the game at this difficulty
     */
    public function pickMany(Game $game, BotDifficulty $difficulty, int $count): array
    {
        $picked = [];
        for ($i = 0; $i < $count; $i++) {
            try {
                $picked[] = $this->pick($game, $difficulty, array_map(fn (BotProfile $b) => $b->id, $picked));
            } catch (InvalidArgumentException $e) {
                $picked[] = $picked[$i % max(1, count($picked))] ?? throw $e;
            }
        }

        return $picked;
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
