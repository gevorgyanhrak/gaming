<?php

namespace App\Gameplay\Bots;

use App\Enums\BotDifficulty;
use App\Models\BotProfile;
use App\Models\Game;
use InvalidArgumentException;

/**
 * The platform's AI players, for every game: who sits down and at what level. AI always plays at the
 * highest difficulty the game has bots for (owner, 2026-11-12: no difficulty choice). Each game's own bot
 * strategy (BotStrategy) decides its moves; this only seats it.
 */
class AiPlayers
{
    public function __construct(private readonly BotSelector $bots) {}

    /**
     * The highest difficulty at least one enabled bot plays the game at (null: the game has no AI).
     */
    public function difficulty(Game $game): ?BotDifficulty
    {
        $available = $this->bots->difficulties($game);

        foreach (array_reverse(BotDifficulty::cases()) as $level) {
            if (in_array($level, $available, true)) {
                return $level;
            }
        }

        return null;
    }

    /**
     * $count AI players for one table, all at the game's highest difficulty, no name twice while the
     * game has enough bots.
     *
     * @return list<array{bot: BotProfile, difficulty: BotDifficulty}>
     *
     * @throws InvalidArgumentException when the game has no AI players
     */
    public function seats(Game $game, int $count): array
    {
        if ($count === 0) {
            return [];
        }
        $difficulty = $this->difficulty($game) ?? throw new InvalidArgumentException('No AI plays this game.');

        return array_map(
            fn (BotProfile $bot) => ['bot' => $bot, 'difficulty' => $difficulty],
            $this->bots->pickMany($game, $difficulty, $count),
        );
    }
}
