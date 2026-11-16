<?php

namespace App\Platform\Slots\House\Games;

/**
 * TROPIC ESCAPE, a 5x3, 9-line slot: the banana is wild and doubles the runs it joins; 2+ suitcases pay, 3+ start
 * 45 free games on their own strips (3+ there add 45 more). Port of APEXSLOTS' tropic-escape engine, maths slotopol
 * "bananasgobahamas": the original 95.364527 % reels (exact, see its test).
 */
final class TropicEscape extends WildLinesFreeGames
{
    public const SYMBOLS = ['BANANA', 'STRAWBERRY', 'MELON', 'PINEAPPLE', 'MANGO', 'COCONUT', 'ACE', 'KING', 'QUEEN', 'JACK', 'TEN', 'NINE', 'SUITCASE'];

    public const BASE = [
        [6, 11, 1, 9, 12, 8, 11, 7, 2, 11, 6, 8, 9, 4, 7, 9, 13, 10, 9, 3, 7, 5, 11, 8, 10, 11, 5, 10, 4, 7],
        [5, 8, 1, 7, 8, 11, 2, 9, 10, 12, 6, 11, 7, 3, 10, 9, 2, 8, 10, 5, 8, 11, 13, 12, 4, 10, 5, 8, 6, 3, 9, 12, 4, 8, 10, 6, 8, 10, 5, 12, 4, 9, 7],
        [11, 9, 1, 11, 4, 8, 9, 2, 8, 6, 10, 4, 7, 12, 2, 10, 11, 9, 12, 13, 9, 11, 5, 11, 3, 10, 9, 4, 7, 5, 10, 11, 2, 7, 6, 12, 7, 3, 11, 10, 6, 7, 3],
        [8, 11, 1, 9, 3, 7, 10, 4, 9, 2, 7, 6, 9, 5, 12, 10, 6, 12, 8, 5, 11, 13, 8, 6, 10, 2, 7, 6, 12, 5, 8, 11, 1, 9, 3, 6, 12, 4, 10, 5, 12, 10, 3],
        [8, 7, 1, 8, 4, 9, 5, 12, 10, 6, 11, 3, 7, 8, 2, 9, 5, 10, 11, 2, 12, 13, 5, 10, 4, 12, 6, 7, 2, 10, 8, 2, 12, 10, 3, 8, 10, 4, 12, 5, 8, 12, 2],
    ];

    public const BONUS = [
        [6, 11, 1, 9, 12, 5, 11, 8, 2, 7, 6, 10, 12, 4, 7, 9, 13, 10, 9, 3, 7, 5, 10, 9, 2, 8, 11, 10, 3, 11, 6, 8, 4, 11, 9],
        [5, 8, 1, 7, 8, 10, 2, 9, 4, 11, 5, 8, 1, 7, 8, 11, 10, 12, 4, 10, 7, 3, 11, 6, 10, 4, 6, 11, 13, 12, 5, 9, 6, 11, 9],
        [11, 9, 1, 11, 4, 7, 8, 2, 6, 11, 9, 1, 11, 4, 7, 12, 10, 9, 8, 2, 12, 10, 5, 12, 3, 6, 7, 12, 13, 9, 10, 5, 3, 7, 5, 10],
        [8, 11, 1, 9, 3, 7, 10, 4, 7, 2, 8, 6, 10, 4, 5, 11, 10, 8, 6, 12, 11, 5, 7, 9, 10, 2, 5, 11, 13, 8, 6, 9, 3, 12, 7, 4],
        [8, 7, 1, 8, 4, 9, 12, 13, 5, 10, 11, 3, 7, 8, 2, 12, 13, 5, 10, 6, 7, 9, 6, 7, 4, 9, 2, 12, 13, 5, 10, 4, 11, 6, 3, 11],
    ];

    public const PAYLINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [1, 2, 2, 2, 1], [1, 0, 0, 0, 1], [2, 2, 1, 0, 0], [0, 0, 1, 2, 2],
    ];

    public const LINE_PAY = [
        'BANANA' => [0, 10, 250, 2500, 9000],
        'STRAWBERRY' => [0, 2, 30, 120, 800],
        'MELON' => [0, 2, 30, 120, 800],
        'PINEAPPLE' => [0, 0, 20, 100, 400],
        'MANGO' => [0, 0, 20, 70, 250],
        'COCONUT' => [0, 0, 20, 70, 250],
        'ACE' => [0, 0, 10, 50, 120],
        'KING' => [0, 0, 10, 50, 120],
        'QUEEN' => [0, 0, 4, 30, 100],
        'JACK' => [0, 0, 4, 30, 100],
        'TEN' => [0, 0, 4, 30, 100],
        'NINE' => [0, 2, 4, 30, 100],
        'SUITCASE' => [0, 0, 0, 0, 0],
    ];

    public const SCAT_PAY = [0, 2, 4, 20, 500];

    public const SCAT_FREESPIN = [0, 0, 45, 45, 45];

    public function id(): string
    {
        return 'tropic-escape';
    }

    public static function symbols(): array
    {
        return self::SYMBOLS;
    }

    public static function baseStrips(): array
    {
        return self::BASE;
    }

    public static function bonusStrips(): array
    {
        return self::BONUS;
    }

    public static function paylines(): array
    {
        return self::PAYLINES;
    }

    public static function linePay(): array
    {
        return self::LINE_PAY;
    }

    public static function scatPay(): array
    {
        return self::SCAT_PAY;
    }

    public static function scatFreeSpins(): array
    {
        return self::SCAT_FREESPIN;
    }

    public static function wild(): string
    {
        return 'BANANA';
    }

    public static function scatter(): string
    {
        return 'SUITCASE';
    }
}
