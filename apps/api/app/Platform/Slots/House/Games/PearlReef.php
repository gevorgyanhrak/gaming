<?php

namespace App\Platform\Slots\House\Games;

/**
 * PEARL REEF, a 5x3, 10-line slot: the turtle is wild and doubles the runs it joins; 2+ clams pay, 3+ start 15 free
 * games on their own strips where every win pays triple. Port of APEXSLOTS' pearl-reef engine, maths slotopol
 * "dolphinspearl": the 95.133189 % reels (exact, see its test).
 */
final class PearlReef extends WildLinesFreeGames
{
    public const FREE_MULT = 3;

    public const SYMBOLS = ['TURTLE', 'SHARK', 'OCTOPUS', 'JELLYFISH', 'PUFFERFISH', 'STARFISH', 'ACE', 'KING', 'QUEEN', 'JACK', 'TEN', 'NINE', 'CLAM'];

    public const BASE = [
        [6, 11, 1, 9, 12, 8, 11, 7, 2, 11, 6, 8, 9, 4, 7, 9, 13, 10, 9, 3, 10, 5, 11, 8, 10, 11, 5, 10, 4, 7],
        [5, 8, 1, 7, 8, 11, 2, 9, 10, 12, 6, 11, 7, 3, 10, 9, 2, 7, 9, 5, 8, 11, 13, 12, 4, 10, 5, 8, 6, 3, 10, 12, 4, 8, 10, 6, 8, 10, 5, 12, 4, 9, 7],
        [11, 9, 1, 11, 4, 7, 2, 12, 6, 4, 10, 3, 12, 5, 11, 10, 2, 9, 12, 13, 9, 11, 3, 7, 5, 9, 6, 4, 8, 2, 10, 5, 7, 3, 12, 4, 9, 6, 7, 8, 5, 7, 6],
        [8, 11, 1, 9, 3, 7, 10, 4, 9, 2, 7, 6, 10, 5, 12, 10, 6, 12, 8, 5, 11, 13, 8, 6, 10, 2, 7, 6, 12, 5, 8, 11, 1, 9, 3, 6, 12, 4, 10, 5, 12, 10, 3],
        [8, 7, 1, 8, 4, 9, 5, 7, 9, 6, 11, 3, 7, 8, 2, 9, 5, 10, 11, 2, 12, 13, 5, 10, 4, 9, 6, 7, 2, 8, 7, 2, 12, 9, 3, 8, 10, 4, 12, 5, 8, 12, 2],
    ];

    public const BONUS = [
        [6, 11, 1, 9, 12, 5, 11, 8, 2, 7, 6, 10, 12, 4, 7, 9, 13, 10, 9, 3, 7, 5, 10, 9, 2, 8, 11, 10, 3, 8, 11, 4, 7],
        [5, 8, 1, 7, 8, 10, 2, 9, 4, 11, 5, 8, 1, 7, 8, 11, 13, 12, 4, 10, 7, 3, 11, 6, 10, 4, 6, 11, 13, 12, 5, 9, 6, 11, 9, 10],
        [11, 9, 1, 11, 4, 7, 8, 2, 6, 11, 9, 1, 11, 4, 8, 12, 13, 9, 8, 2, 12, 10, 5, 12, 3, 6, 7, 12, 13, 9, 10, 5, 3, 7, 5, 10],
        [8, 11, 1, 9, 3, 7, 10, 4, 7, 2, 8, 6, 10, 4, 5, 11, 13, 8, 6, 12, 11, 5, 7, 9, 10, 2, 5, 11, 13, 8, 6, 9, 3, 12, 7, 4],
        [8, 7, 1, 8, 4, 9, 5, 12, 7, 6, 11, 3, 7, 8, 2, 12, 13, 5, 10, 7, 8, 9, 6, 10, 4, 9, 2, 12, 13, 5, 10, 4, 11, 6, 3, 11],
    ];

    public const PAYLINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [1, 2, 2, 2, 1], [1, 0, 0, 0, 1], [2, 2, 1, 0, 0], [0, 0, 1, 2, 2], [2, 1, 1, 1, 0],
    ];

    public const LINE_PAY = [
        'TURTLE' => [0, 10, 250, 2500, 9000],
        'SHARK' => [0, 2, 25, 125, 750],
        'OCTOPUS' => [0, 2, 25, 125, 750],
        'JELLYFISH' => [0, 0, 20, 100, 400],
        'PUFFERFISH' => [0, 0, 15, 75, 250],
        'STARFISH' => [0, 0, 15, 75, 250],
        'ACE' => [0, 0, 10, 50, 125],
        'KING' => [0, 0, 10, 50, 125],
        'QUEEN' => [0, 0, 5, 25, 100],
        'JACK' => [0, 0, 5, 25, 100],
        'TEN' => [0, 0, 5, 25, 100],
        'NINE' => [0, 2, 5, 25, 100],
        'CLAM' => [0, 0, 0, 0, 0],
    ];

    public const SCAT_PAY = [0, 2, 5, 20, 500];

    public const SCAT_FREESPIN = [0, 0, 15, 15, 15];

    public function id(): string
    {
        return 'pearl-reef';
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
        return 'TURTLE';
    }

    public static function scatter(): string
    {
        return 'CLAM';
    }
}
