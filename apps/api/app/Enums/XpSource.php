<?php

namespace App\Enums;

/**
 * What a player earned XP for (ADR 0018).
 */
enum XpSource: string
{
    /** A finished game (taking part, plus a win or a draw). */
    case Game = 'game';
    /** Collecting the daily bonus. */
    case DailyBonus = 'daily_bonus';
}
