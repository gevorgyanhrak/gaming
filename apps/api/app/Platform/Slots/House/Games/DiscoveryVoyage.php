<?php

namespace App\Platform\Slots\House\Games;

/**
 * DISCOVERY VOYAGE, a 5x3, 10-line slot: the captain is wild and the top symbol (no multiplier); 3+ galleons
 * anywhere start 10 free games on their own strips, where the galleon is wild as well (and 3+ add 10 more). The
 * galleon pays nothing itself. Port of APEXSLOTS' discovery-voyage engine, maths slotopol "columbus": the
 * 95.022795 % reels (exact, see its test). Spins carry `scatterHit` and `freeGame` as the client expects.
 */
final class DiscoveryVoyage extends WildLinesFreeGames
{
    public const WILD_MULT = 1;

    public const SYMBOLS = ['CAPTAIN', 'CROWN', 'CHEST', 'COMPASS', 'ACE', 'KING', 'QUEEN', 'JACK', 'TEN', 'GALLEON'];

    public const BASE = [
        [1, 5, 7, 9, 8, 2, 10, 6, 4, 10, 6, 3, 4, 8, 9, 10, 7, 8, 9, 7, 8, 9, 3, 8, 9, 7, 5, 9, 7, 2, 5, 9],
        [1, 7, 6, 3, 7, 4, 6, 9, 3, 8, 5, 6, 2, 9, 6, 8, 5, 2, 8, 5, 8, 6, 9, 8, 4, 5, 8, 6],
        [1, 6, 8, 4, 7, 5, 9, 6, 7, 9, 5, 6, 8, 7, 5, 3, 9, 6, 8, 7, 6, 9, 7, 5, 8, 4, 10, 7, 9, 2, 10, 9],
        [1, 6, 4, 8, 1, 7, 9, 4, 6, 8, 9, 3, 5, 2, 6, 3, 9, 5, 7, 1, 8, 9, 2, 5, 7, 4, 5, 8],
        [10, 6, 8, 9, 6, 10, 5, 6, 4, 1, 8, 7, 1, 8, 9, 3, 7, 2, 6, 8, 9, 4, 7, 9, 3, 7, 5, 3, 9, 4, 7, 5],
    ];

    public const BONUS = [
        [5, 1, 9, 8, 2, 10, 6, 5, 10, 8, 3, 4, 8, 5, 10, 7, 8, 5, 9, 8, 5, 3, 6, 5, 8],
        [7, 9, 3, 7, 4, 6, 9, 7, 8, 9, 7, 8, 9, 7, 8, 5, 2, 8, 5, 4, 9, 6, 7, 9, 1],
        [6, 8, 4, 7, 5, 9, 6, 2, 9, 5, 6, 8, 10, 6, 3, 9, 6, 8, 7, 5, 10, 6, 8, 1],
        [6, 9, 1, 6, 7, 9, 4, 2, 8, 9, 3, 8, 7, 3, 4, 9, 5, 4, 6, 3, 5, 8, 3, 9, 1],
        [7, 4, 9, 6, 10, 9, 6, 4, 9, 8, 7, 1, 8, 9, 3, 7, 2, 9, 3, 8, 10, 4, 5, 2],
    ];

    public const PAYLINES = [
        [1, 1, 1, 1, 1], [0, 0, 0, 0, 0], [2, 2, 2, 2, 2], [0, 1, 2, 1, 0], [2, 1, 0, 1, 2],
        [1, 2, 2, 2, 1], [1, 0, 0, 0, 1], [2, 2, 1, 0, 0], [0, 0, 1, 2, 2], [2, 1, 1, 1, 0],
    ];

    public const LINE_PAY = [
        'CAPTAIN' => [0, 10, 100, 1000, 5000],
        'CROWN' => [0, 5, 50, 200, 1000],
        'CHEST' => [0, 5, 25, 100, 500],
        'COMPASS' => [0, 5, 15, 75, 250],
        'ACE' => [0, 0, 10, 40, 150],
        'KING' => [0, 0, 10, 40, 150],
        'QUEEN' => [0, 0, 10, 40, 150],
        'JACK' => [0, 0, 5, 20, 100],
        'TEN' => [0, 0, 5, 20, 100],
        'GALLEON' => [0, 0, 0, 0, 0],
    ];

    public const SCAT_FREESPIN = [0, 0, 10, 10, 10];

    public function id(): string
    {
        return 'discovery-voyage';
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
        return [0, 0, 0, 0, 0];
    }

    public static function scatFreeSpins(): array
    {
        return self::SCAT_FREESPIN;
    }

    public static function wild(): string
    {
        return 'CAPTAIN';
    }

    public static function scatter(): string
    {
        return 'GALLEON';
    }

    protected static function isWild(string $symbol, bool $freeGame): bool
    {
        return $symbol === 'CAPTAIN' || ($freeGame && $symbol === 'GALLEON');
    }

    public static function evaluate(array $grid, bool $freeGame = false): array
    {
        $spin = parent::evaluate($grid, $freeGame);
        $scatters = [];
        foreach ($grid as $reel => $column) {
            foreach ($column as $row => $symbol) {
                if ($symbol === 'GALLEON') {
                    $scatters[] = [$reel, $row];
                }
            }
        }
        $free = $scatters === [] ? 0 : self::SCAT_FREESPIN[min(count($scatters), 5) - 1];
        unset($spin['scatterWin']);
        $total = $spin['totalWin'];
        unset($spin['totalWin'], $spin['freeSpinsWon']);
        if ($free > 0) {
            $spin['scatterHit'] = ['count' => count($scatters), 'positions' => $scatters];
        }

        return [...$spin, 'freeSpinsWon' => $free, 'freeGame' => $freeGame, 'totalWin' => (float) $total];
    }
}
