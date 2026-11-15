<?php

namespace App\Gameplay\Bots;

use App\Enums\BotPersonality;

/**
 * How long an AI player takes over a move, so it feels like a person at the table (owner, 2026-11-16) — shared by
 * every game, no game rules here:
 * - more choices take longer (an obvious or forced move is quick, a wide one takes a few seconds);
 * - no two thoughts are alike, and now and then it stops to consider (a longer pause);
 * - its personality's pace and spread (cautious slower, bold quicker, tricky hard to read);
 * - several AI players due at once don't move together: each waits a little after the one before;
 * - it never runs into the turn timer.
 */
final class HumanPace
{
    /** A forced or obvious move. */
    private const QUICK = [700, 1300];

    private const BASE_MS = 1100;

    /** Per doubling of the choices. */
    private const PER_DOUBLING_MS = 500;

    private const CAP_MS = 6500;

    /** Percent of moves it stops to consider, and how much longer then. */
    private const PONDER_PERCENT = 12;

    /**
     * @param  int  $choices  How many legal actions it has.
     * @param  int|null  $baseMs  The game bot's own estimate (PacesThinking), used instead of the choices.
     * @param  int  $queue  Its place among the AI players due at the same time (0 = first).
     * @param  int|null  $msLeft  Time left on the turn timer, if there is one.
     */
    public static function thinkingMs(int $choices, ?BotPersonality $personality, int $queue = 0, ?int $baseMs = null, ?int $msLeft = null): int
    {
        if ($baseMs !== null) {
            $ms = $baseMs;
        } elseif ($choices <= 1) {
            $ms = random_int(...self::QUICK);
        } else {
            $ms = (int) min(self::CAP_MS, self::BASE_MS + self::PER_DOUBLING_MS * log($choices, 2));
            $ms = (int) round($ms * random_int(70, 140) / 100);
            if (random_int(1, 100) <= self::PONDER_PERCENT) {
                $ms = (int) round($ms * random_int(160, 220) / 100);
            }
        }

        if ($personality !== null && $ms > 0) {
            $spread = $personality->jitter();
            $ms = (int) round($ms * $personality->tempo() * (1 + $spread * (random_int(-100, 100) / 100)));
        }

        for ($i = 0; $i < $queue; $i++) {
            $ms += random_int(500, 1200);
        }

        if ($msLeft !== null) {
            $ms = min($ms, max(400, $msLeft - 2500));
        }

        return max(0, $ms);
    }
}
