<?php

namespace App\Games\Nardi\Engine;

/**
 * Short Nardi (backgammon rules): positions, legal steps and their effects. Pure functions on the
 * state array; no randomness or turn handling (see NardiEngine).
 *
 * Board: points 1..24. Seat 0 moves from 24 down to 1 (home 1..6, bears off past 1); seat 1 moves
 * from 1 up to 24 (home 19..24, bears off past 24). `points` holds 24 signed counts (index 0 =
 * point 1): positive for seat 0, negative for seat 1.
 *
 * A step is ['from' => int|'bar', 'to' => int|'off', 'die' => int].
 */
final class NardiRules
{
    public const CHECKERS = 15;

    /**
     * @return list<int>
     */
    public static function startingPoints(): array
    {
        $points = array_fill(0, 24, 0);
        foreach ([24 => 2, 13 => 5, 8 => 3, 6 => 5] as $point => $count) {
            $points[$point - 1] = $count;          // seat 0
            $points[25 - $point - 1] = -$count;    // seat 1, mirrored
        }

        return $points;
    }

    /**
     * Own checkers of $seat on point $p.
     *
     * @param  array<string, mixed>  $state
     */
    public static function own(array $state, int $seat, int $p): int
    {
        $n = $state['points'][$p - 1];

        return $seat === 0 ? max(0, $n) : max(0, -$n);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function opponent(array $state, int $seat, int $p): int
    {
        return self::own($state, 1 - $seat, $p);
    }

    public static function inHome(int $seat, int $p): bool
    {
        return $seat === 0 ? $p <= 6 : $p >= 19;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function canBearOff(array $state, int $seat): bool
    {
        if ($state['bar'][$seat] > 0) {
            return false;
        }

        for ($p = 1; $p <= 24; $p++) {
            if (self::own($state, $seat, $p) > 0 && ! self::inHome($seat, $p)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Every single step the seat could make with one of the dice values, ignoring the
     * "use as many dice as possible" rule (see NardiEngine::legalSteps).
     *
     * @param  list<int>  $dice
     * @param  array<string, mixed>  $state
     * @return list<array{from: int|string, to: int|string, die: int}>
     */
    public static function singleSteps(array $state, int $seat, array $dice): array
    {
        $steps = [];

        foreach (array_values(array_unique($dice)) as $die) {
            if ($state['bar'][$seat] > 0) {
                $to = $seat === 0 ? 25 - $die : $die;
                if (self::opponent($state, $seat, $to) < 2) {
                    $steps[] = ['from' => 'bar', 'to' => $to, 'die' => $die];
                }

                continue;
            }

            $bearOff = self::canBearOff($state, $seat);

            for ($p = 1; $p <= 24; $p++) {
                if (self::own($state, $seat, $p) === 0) {
                    continue;
                }

                $to = $seat === 0 ? $p - $die : $p + $die;

                if ($to >= 1 && $to <= 24) {
                    if (self::opponent($state, $seat, $to) < 2) {
                        $steps[] = ['from' => $p, 'to' => $to, 'die' => $die];
                    }
                } elseif ($bearOff && (($to === 0 || $to === 25) || ! self::hasCheckerFarther($state, $seat, $p))) {
                    // Exact bear-off, or a higher die from the farthest checker.
                    $steps[] = ['from' => $p, 'to' => 'off', 'die' => $die];
                }
            }
        }

        return $steps;
    }

    /**
     * Whether the seat has a checker farther from bearing off than point $p.
     *
     * @param  array<string, mixed>  $state
     */
    private static function hasCheckerFarther(array $state, int $seat, int $p): bool
    {
        $range = $seat === 0 ? range($p + 1, 24) : ($p > 1 ? range(1, $p - 1) : []);

        foreach ($range as $q) {
            if (self::own($state, $seat, $q) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply a step (assumed legal). Returns the new state and whether a checker was hit.
     *
     * @param  array{from: int|string, to: int|string, die: int}  $step
     * @param  array<string, mixed>  $state
     * @return array{0: array<string, mixed>, 1: bool}
     */
    public static function applyStep(array $state, int $seat, array $step): array
    {
        $sign = $seat === 0 ? 1 : -1;
        $hit = false;

        if ($step['from'] === 'bar') {
            $state['bar'][$seat]--;
        } else {
            $state['points'][$step['from'] - 1] -= $sign;
        }

        if ($step['to'] === 'off') {
            $state['off'][$seat]++;
        } else {
            $to = $step['to'] - 1;
            if ($state['points'][$to] === -$sign) {
                $state['points'][$to] = 0;
                $state['bar'][1 - $seat]++;
                $hit = true;
            }
            $state['points'][$to] += $sign;
        }

        return [$state, $hit];
    }

    /**
     * @param  list<int>  $dice
     * @return list<int>
     */
    public static function withoutDie(array $dice, int $die): array
    {
        $i = array_search($die, $dice, true);
        if ($i !== false) {
            unset($dice[$i]);
        }

        return array_values($dice);
    }

    /**
     * Pips the seat still has to travel (lower is better).
     *
     * @param  array<string, mixed>  $state
     */
    public static function pips(array $state, int $seat): int
    {
        $pips = 25 * $state['bar'][$seat];
        for ($p = 1; $p <= 24; $p++) {
            $pips += self::own($state, $seat, $p) * ($seat === 0 ? $p : 25 - $p);
        }

        return $pips;
    }

    /**
     * The most dice the seat can play from here (0..count($dice)).
     *
     * @param  list<int>  $dice
     * @param  array<string, int>  $memo
     * @param  array<string, mixed>  $state
     */
    public static function maxPlayable(array $state, int $seat, array $dice, array &$memo = []): int
    {
        if ($dice === []) {
            return 0;
        }

        $key = self::key($state, $dice);
        if (isset($memo[$key])) {
            return $memo[$key];
        }

        $best = 0;
        foreach (self::singleSteps($state, $seat, $dice) as $step) {
            [$next] = self::applyStep($state, $seat, $step);
            $best = max($best, 1 + self::maxPlayable($next, $seat, self::withoutDie($dice, $step['die']), $memo));
            if ($best === count($dice)) {
                break;
            }
        }

        return $memo[$key] = $best;
    }

    /**
     * @param  list<int>  $dice
     * @param  array<string, mixed>  $state
     */
    public static function key(array $state, array $dice): string
    {
        sort($dice);

        return implode(',', $state['points']).'|'.implode(',', $state['bar']).'|'.implode(',', $state['off']).'|'.implode(',', $dice);
    }
}
