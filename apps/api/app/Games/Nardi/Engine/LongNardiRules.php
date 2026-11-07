<?php

namespace App\Games\Nardi\Engine;

/**
 * Long Nardi (длинные нарды), as played in Armenia and Russia. Same board and state shape as Short
 * Nardi (NardiRules), different rules:
 *
 * - Each player's 15 checkers start on one point, the head: seat 0 on point 24, seat 1 on point 12.
 * - Both move the same way round the board: seat 0 from 24 down to 1, seat 1 from 12 down to 1 and on
 *   from 24 down to 13. Home is the last quarter of a player's way (seat 0: 6..1, seat 1: 18..13).
 * - No hitting: a point with any opponent checker on it is closed. `bar` stays [0, 0].
 * - Only one checker may leave the head in a turn — except a player's first turn with 6-6, 4-4 or 3-3,
 *   when two may (`head_moves` counts this turn's, `turns` the turns each player has had).
 * - No block of six points in a row unless an opponent checker is already ahead of it.
 * - Bearing off once all 15 are home: the exact die, or a higher one from the farthest checker.
 *
 * Positions are counted along each player's way: 1 = the head, 24 = the last point before bearing off.
 */
class LongNardiRules extends NardiRules
{
    /** First-turn doubles that let two checkers leave the head. */
    private const HEAD_DOUBLES = [3, 4, 6];

    /**
     * @return list<int>
     */
    public static function startingPoints(): array
    {
        $points = array_fill(0, 24, 0);
        $points[24 - 1] = self::CHECKERS;      // seat 0's head
        $points[12 - 1] = -self::CHECKERS;     // seat 1's head

        return $points;
    }

    /** Where board point $point is on $seat's way (1 = head .. 24). */
    public static function position(int $seat, int $point): int
    {
        return $seat === 0 ? 25 - $point : ($point <= 12 ? 13 - $point : 37 - $point);
    }

    /** The board point at position $position of $seat's way. */
    public static function point(int $seat, int $position): int
    {
        return $seat === 0 ? 25 - $position : ($position <= 12 ? 13 - $position : 37 - $position);
    }

    public static function inHome(int $seat, int $p): bool
    {
        return self::position($seat, $p) >= 19;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function canBearOff(array $state, int $seat): bool
    {
        for ($p = 1; $p <= 24; $p++) {
            if (static::own($state, $seat, $p) > 0 && ! static::inHome($seat, $p)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<int>  $dice
     * @param  array<string, mixed>  $state
     * @return list<array{from: int|string, to: int|string, die: int}>
     */
    public static function singleSteps(array $state, int $seat, array $dice): array
    {
        $steps = [];
        $bearOff = static::canBearOff($state, $seat);
        $head = self::point($seat, 1);
        $headOpen = ($state['head_moves'] ?? 0) < self::headLimit($state, $seat);

        foreach (array_values(array_unique($dice)) as $die) {
            for ($p = 1; $p <= 24; $p++) {
                if (static::own($state, $seat, $p) === 0 || ($p === $head && ! $headOpen)) {
                    continue;
                }

                $at = self::position($seat, $p);
                $next = $at + $die;

                if ($next <= 24) {
                    $to = self::point($seat, $next);
                    if (static::opponent($state, $seat, $to) === 0) {
                        $steps[] = ['from' => $p, 'to' => $to, 'die' => $die];
                    }
                } elseif ($bearOff && ($next === 25 || ! static::hasCheckerFarther($state, $seat, $p))) {
                    $steps[] = ['from' => $p, 'to' => 'off', 'die' => $die];
                }
            }
        }

        // A step may not wall the opponent in (six in a row with none of theirs ahead).
        return array_values(array_filter(
            $steps,
            fn (array $step) => ! self::walls(static::applyStep($state, $seat, $step)[0], $seat),
        ));
    }

    /**
     * Apply a step (assumed legal); nothing is ever hit.
     *
     * @param  array{from: int|string, to: int|string, die: int}  $step
     * @param  array<string, mixed>  $state
     * @return array{0: array<string, mixed>, 1: bool}
     */
    public static function applyStep(array $state, int $seat, array $step): array
    {
        $sign = $seat === 0 ? 1 : -1;
        $state['points'][$step['from'] - 1] -= $sign;
        if ($step['from'] === self::point($seat, 1)) {
            $state['head_moves'] = ($state['head_moves'] ?? 0) + 1;
        }

        if ($step['to'] === 'off') {
            $state['off'][$seat]++;
        } else {
            $state['points'][$step['to'] - 1] += $sign;
        }

        return [$state, false];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function pips(array $state, int $seat): int
    {
        $pips = 0;
        for ($p = 1; $p <= 24; $p++) {
            $pips += static::own($state, $seat, $p) * (25 - self::position($seat, $p));
        }

        return $pips;
    }

    /**
     * @param  list<int>  $dice
     * @param  array<string, mixed>  $state
     */
    public static function key(array $state, array $dice): string
    {
        return parent::key($state, $dice).'|'.($state['head_moves'] ?? 0);
    }

    /**
     * How many checkers may leave $seat's head this turn.
     *
     * @param  array<string, mixed>  $state
     */
    public static function headLimit(array $state, int $seat): int
    {
        [$a, $b] = $state['rolled'] + [0, 0];
        $first = ($state['turns'][$seat] ?? 0) === 0;

        return $first && $a === $b && in_array($a, self::HEAD_DOUBLES, true) ? 2 : 1;
    }

    /**
     * Whether $seat has six points in a row on the opponent's way with no opponent checker ahead of
     * them (an opponent checker already past the wall makes it allowed).
     *
     * @param  array<string, mixed>  $state
     */
    public static function walls(array $state, int $seat): bool
    {
        $opp = 1 - $seat;
        $farthest = 0;     // the opponent's checker farthest along their way
        for ($p = 1; $p <= 24; $p++) {
            if (static::own($state, $opp, $p) > 0) {
                $farthest = max($farthest, self::position($opp, $p));
            }
        }

        $run = 0;
        for ($at = 1; $at <= 24; $at++) {
            $run = static::own($state, $seat, self::point($opp, $at)) > 0 ? $run + 1 : 0;
            if ($run >= 6 && $farthest <= $at) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether $seat has a checker farther from bearing off than point $p (earlier on their way).
     *
     * @param  array<string, mixed>  $state
     */
    protected static function hasCheckerFarther(array $state, int $seat, int $p): bool
    {
        $at = self::position($seat, $p);
        for ($q = 1; $q <= 24; $q++) {
            if (static::own($state, $seat, $q) > 0 && self::position($seat, $q) < $at) {
                return true;
            }
        }

        return false;
    }
}
