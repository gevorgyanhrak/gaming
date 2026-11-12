<?php

namespace App\Games\Blot\Bot;

use App\Enums\BotDifficulty;

/**
 * How well a blot bot judges a Quansh (contra). The platform seats three difficulties; the owner chose to map
 * them onto Beginner, Advanced and Expert (2026-11-12). Intermediate is a step between, kept for tests.
 */
enum QuanshSkill: string
{
    /** Judges as Intermediate but overlooks about a third of its chances. */
    case Beginner = 'beginner';

    /** Weighs its own defence and its partner's likely share against the bid. */
    case Intermediate = 'intermediate';

    /** Plays the deal out over possible hands that fit the bids, and Quanshes when it pays. */
    case Advanced = 'advanced';

    /** As Advanced with more samples, reading passes too, the risk of a Sur and the match score. */
    case Expert = 'expert';

    public static function of(BotDifficulty $difficulty): self
    {
        return match ($difficulty) {
            BotDifficulty::Easy => self::Beginner,
            BotDifficulty::Medium => self::Advanced,
            BotDifficulty::Hard => self::Expert,
        };
    }
}
