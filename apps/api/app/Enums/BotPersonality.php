<?php

namespace App\Enums;

/**
 * An AI player's character (owner 2026-11-15: "different AI personalities and playing styles"). It changes pace
 * everywhere and style where a game leaves room for one (PlaysInStyle: poker's bluffing and raising, blackjack's
 * bet size) — never the strength of the play: every AI still plays its game's best moves.
 */
enum BotPersonality: string
{
    /** Takes its time, bets small, bluffs rarely. */
    case Cautious = 'cautious';
    /** Quick, raises and bets big. */
    case Bold = 'bold';
    /** Unpredictable pace, bluffs the most. */
    case Tricky = 'tricky';
    /** Even pace, by the book. */
    case Steady = 'steady';

    /** A stable personality for a name: the same bot is always the same character. */
    public static function for(string $name): self
    {
        $all = self::cases();

        return $all[crc32($name) % count($all)];
    }

    /** How much longer (> 1) or shorter (< 1) it thinks than its game's usual pace. */
    public function tempo(): float
    {
        return match ($this) {
            self::Cautious => 1.35,
            self::Bold => 0.7,
            self::Tricky, self::Steady => 1.0,
        };
    }

    /** Extra spread of its thinking time (Tricky is hard to read). */
    public function jitter(): float
    {
        return $this === self::Tricky ? 0.45 : 0.1;
    }

    /**
     * The share of moves (in percent) it plays a level below its best — a person's lapses: misjudging a hand,
     * missing a better move. Never a random move.
     */
    public function lapsePercent(): int
    {
        return match ($this) {
            self::Steady => 6,
            self::Cautious => 8,
            self::Bold => 10,
            self::Tricky => 12,
        };
    }
}
