<?php

namespace App\Platform\Achievements;

/**
 * The achievements, in code (like the level items): each is earned from the server's own records — game_wins
 * (the ledger's results), user_game_stats and the leaderboards — never from anything a client says.
 * Tournament titles wait for tournaments (none exist).
 */
final class AchievementCatalog
{
    /** Won at least this many Fishkas in one game of a main game: "Big Blackjack/Poker/Blot win". */
    public const BIG_WIN = 10_000;

    /** The main games with a "big win" achievement each. */
    public const BIG_WIN_GAMES = ['blackjack', 'poker', 'blot'];

    /** Wins in a row in one game. */
    public const STREAK = 5;

    /** Games played in all, for the "experienced player" tiers. */
    public const VETERAN = [100, 500, 1000];

    /**
     * Every key: big_win (per game), streak (per game), veteran_100/500/1000, daily_best, weekly_champion.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return ['big_win', 'streak', ...array_map(fn (int $n) => "veteran_{$n}", self::VETERAN), 'daily_best', 'weekly_champion'];
    }
}
