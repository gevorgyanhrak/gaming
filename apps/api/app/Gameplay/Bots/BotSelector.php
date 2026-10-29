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
     * $count bots for one table, every one a different bot (so no two seats share a name). When too few
     * bots play the game at this difficulty, other bots that play the game fill the seats; they still
     * play at the table's difficulty, which is set per seat. Only when the game has fewer bots than
     * seats does the same bot sit down more than once.
     *
     * @return list<BotProfile>
     *
     * @throws InvalidArgumentException when no bot plays the game at this difficulty
     */
    public function pickMany(Game $game, BotDifficulty $difficulty, int $count): array
    {
        $picked = [];

        for ($i = 0; $i < $count; $i++) {
            $seated = array_map(fn (BotProfile $b) => $b->id, $picked);
            try {
                $picked[] = $this->pick($game, $difficulty, $seated);
            } catch (InvalidArgumentException $e) {
                if ($picked === []) {
                    throw $e;
                }
                $picked[] = $this->anyFor($game, $seated) ?? $picked[$i % count($picked)];
            }
        }

        return $picked;
    }

    /**
     * Games and difficulties with fewer enabled bots than their biggest table needs, for the admin panel.
     *
     * @return list<array{game: string, difficulty: BotDifficulty, bots: int, needed: int}>
     */
    public function shortages(): array
    {
        $shortages = [];
        foreach (Game::query()->where('is_enabled', true)->orderBy('sort_order')->get() as $game) {
            $needed = max(1, $game->max_players - 1);
            foreach (BotDifficulty::cases() as $difficulty) {
                $bots = BotProfile::query()->where('is_enabled', true)
                    ->whereHas('gameProfiles', fn ($q) => $q->where('game_id', $game->id)->where('difficulty', $difficulty)->where('is_enabled', true))
                    ->count();
                if ($bots > 0 && $bots < $needed) {
                    $shortages[] = ['game' => $game->name, 'difficulty' => $difficulty, 'bots' => $bots, 'needed' => $needed];
                }
            }
        }

        return $shortages;
    }

    /**
     * Any enabled bot that plays the game, at whatever difficulty, other than those already seated.
     *
     * @param  list<int>  $exclude
     */
    private function anyFor(Game $game, array $exclude): ?BotProfile
    {
        return BotProfile::query()
            ->where('is_enabled', true)
            ->whereNotIn('id', $exclude)
            ->whereHas('gameProfiles', fn ($q) => $q->where('game_id', $game->id)->where('is_enabled', true))
            ->inRandomOrder()
            ->first();
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
