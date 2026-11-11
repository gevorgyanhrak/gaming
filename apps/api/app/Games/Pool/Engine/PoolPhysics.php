<?php

namespace App\Games\Pool\Engine;

/**
 * A deterministic pool table simulation in millimetres and seconds (a 9-foot table, 2540 × 1270, balls
 * 57.15 across). Balls roll in straight lines and slow down at a constant rate (cloth friction), collide
 * elastically with each other (a little energy lost) and bounce off the cushions; there are no cushions in
 * the pocket mouths, and a ball whose centre goes past the cushion line there drops in. No spin.
 *
 * Fixed 2 ms steps, so the same shot from the same position always ends the same way (replays). Besides
 * the final positions the result describes each ball's path as segments — the time (ms), position and
 * velocity whenever its velocity changed abruptly — so a client can draw the shot without sending frames:
 * between segments a ball moves along its velocity, slowing by DECEL until it stops.
 */
final class PoolPhysics
{
    public const W = 2540.0;

    public const H = 1270.0;

    public const R = 28.575;

    /** Rolling deceleration, mm/s². */
    public const DECEL = 420.0;

    /** Cue ball speed at full power, mm/s. */
    public const MAX_SPEED = 4600.0;

    private const BALL_E = 0.95;

    private const RAIL_E = 0.78;

    private const DT = 0.002;

    private const MAX_STEPS = 9000;

    /** How far from a corner along each rail the corner pocket mouth reaches, and half a side pocket's mouth. */
    private const CORNER_MOUTH = 2.4 * self::R;

    private const SIDE_MOUTH = 2.2 * self::R;

    /**
     * Strike the cue ball (id 0) at $angle degrees (0 = towards the foot rail, counter-clockwise on screen
     * with y down means clockwise) with $power 1–100.
     *
     * @param  array<int, array{0: float, 1: float}|null>  $balls  id → [x, y], null when off the table.
     * @return array{balls: array<int, array{0: float, 1: float}|null>, potted: list<int>, first_contact: int|null, rail_after_contact: bool, rails: int, segments: array<int, list<list<float|int>>>, duration_ms: int}
     */
    public static function shoot(array $balls, float $angle, float $power): array
    {
        $pos = [];
        $vel = [];
        foreach ($balls as $id => $ball) {
            if ($ball !== null) {
                $pos[$id] = [(float) $ball[0], (float) $ball[1]];
                $vel[$id] = [0.0, 0.0];
            }
        }
        $speed = max(1.0, min(100.0, $power)) / 100 * self::MAX_SPEED;
        $rad = deg2rad($angle);
        $vel[0] = [$speed * cos($rad), $speed * sin($rad)];

        $segments = [0 => [self::segment(0, $pos[0], $vel[0])]];
        $potted = [];
        $firstContact = null;
        $railAfterContact = false;
        $rails = 0;
        $step = 0;

        for (; $step < self::MAX_STEPS; $step++) {
            $t = (int) round(($step + 1) * self::DT * 1000);
            $moving = false;
            foreach ($vel as $id => [$vx, $vy]) {
                $s = sqrt($vx * $vx + $vy * $vy);
                if ($s <= 0.0) {
                    continue;
                }
                $moving = true;
                $next = max(0.0, $s - self::DECEL * self::DT);
                $vel[$id] = $next <= 0.0 ? [0.0, 0.0] : [$vx * $next / $s, $vy * $next / $s];
                $pos[$id][0] += $vel[$id][0] * self::DT;
                $pos[$id][1] += $vel[$id][1] * self::DT;

                if (self::dropsIn($pos[$id])) {
                    $potted[] = $id;
                    $segments[$id][] = self::segment($t, $pos[$id], [0.0, 0.0], potted: true);
                    unset($pos[$id], $vel[$id]);
                    if ($firstContact !== null) {
                        $railAfterContact = true;
                    }

                    continue;
                }
                if (self::cushion($pos[$id], $vel[$id])) {
                    $rails++;
                    if ($firstContact !== null) {
                        $railAfterContact = true;
                    }
                    $segments[$id][] = self::segment($t, $pos[$id], $vel[$id]);
                }
            }
            if (! $moving) {
                break;
            }

            $ids = array_keys($pos);
            $count = count($ids);
            for ($a = 0; $a < $count; $a++) {
                for ($b = $a + 1; $b < $count; $b++) {
                    $i = $ids[$a];
                    $j = $ids[$b];
                    if ($vel[$i] === [0.0, 0.0] && $vel[$j] === [0.0, 0.0]) {
                        continue;
                    }
                    $dx = $pos[$j][0] - $pos[$i][0];
                    $dy = $pos[$j][1] - $pos[$i][1];
                    $d2 = $dx * $dx + $dy * $dy;
                    if ($d2 >= 4 * self::R * self::R || $d2 === 0.0) {
                        continue;
                    }
                    $d = sqrt($d2);
                    [$nx, $ny] = [$dx / $d, $dy / $d];
                    $approach = ($vel[$i][0] - $vel[$j][0]) * $nx + ($vel[$i][1] - $vel[$j][1]) * $ny;
                    // Push the balls apart so they just touch.
                    $overlap = (2 * self::R - $d) / 2;
                    $pos[$i] = [$pos[$i][0] - $nx * $overlap, $pos[$i][1] - $ny * $overlap];
                    $pos[$j] = [$pos[$j][0] + $nx * $overlap, $pos[$j][1] + $ny * $overlap];
                    if ($approach <= 0) {
                        continue;
                    }
                    $impulse = (1 + self::BALL_E) / 2 * $approach;
                    $vel[$i] = [$vel[$i][0] - $impulse * $nx, $vel[$i][1] - $impulse * $ny];
                    $vel[$j] = [$vel[$j][0] + $impulse * $nx, $vel[$j][1] + $impulse * $ny];
                    if ($firstContact === null && ($i === 0 || $j === 0)) {
                        $firstContact = $i === 0 ? $j : $i;
                    }
                    $segments[$i][] = self::segment($t, $pos[$i], $vel[$i]);
                    $segments[$j][] = self::segment($t, $pos[$j], $vel[$j]);
                }
            }
        }

        $final = [];
        foreach (array_keys($balls) as $id) {
            $final[$id] = isset($pos[$id]) ? [round($pos[$id][0], 1), round($pos[$id][1], 1)] : null;
        }

        return [
            'balls' => $final,
            'potted' => $potted,
            'first_contact' => $firstContact,
            'rail_after_contact' => $railAfterContact,
            'rails' => $rails,
            'segments' => $segments,
            'duration_ms' => (int) round($step * self::DT * 1000),
        ];
    }

    /**
     * Whether a ball at [x, y] lies within the table, clear of every ball in $balls (but $except).
     *
     * @param  array<int, array{0: float, 1: float}|null>  $balls
     */
    public static function free(array $balls, float $x, float $y, ?int $except = 0): bool
    {
        if ($x < self::R || $x > self::W - self::R || $y < self::R || $y > self::H - self::R) {
            return false;
        }
        foreach ($balls as $id => $ball) {
            if ($ball !== null && $id !== $except && (($ball[0] - $x) ** 2 + ($ball[1] - $y) ** 2) < (2 * self::R) ** 2) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{0: float, 1: float}  $p
     */
    private static function dropsIn(array $p): bool
    {
        [$x, $y] = $p;
        $deep = 0.3 * self::R;

        return (($y < $deep || $y > self::H - $deep) && self::inLongMouth($x))
            || (($x < $deep || $x > self::W - $deep) && self::inShortMouth($y));
    }

    /**
     * Bounce off a cushion (outside the pocket mouths). Returns whether it did.
     *
     * @param  array{0: float, 1: float}  $p
     * @param  array{0: float, 1: float}  $v
     */
    private static function cushion(array &$p, array &$v): bool
    {
        $hit = false;
        if (! self::inLongMouth($p[0])) {
            if ($p[1] < self::R && $v[1] < 0) {
                [$p[1], $v[1], $hit] = [self::R, -$v[1] * self::RAIL_E, true];
            } elseif ($p[1] > self::H - self::R && $v[1] > 0) {
                [$p[1], $v[1], $hit] = [self::H - self::R, -$v[1] * self::RAIL_E, true];
            }
        }
        if (! self::inShortMouth($p[1])) {
            if ($p[0] < self::R && $v[0] < 0) {
                [$p[0], $v[0], $hit] = [self::R, -$v[0] * self::RAIL_E, true];
            } elseif ($p[0] > self::W - self::R && $v[0] > 0) {
                [$p[0], $v[0], $hit] = [self::W - self::R, -$v[0] * self::RAIL_E, true];
            }
        }

        return $hit;
    }

    /** Along a long rail: within a corner pocket's mouth or the side pocket's. */
    private static function inLongMouth(float $x): bool
    {
        return $x < self::CORNER_MOUTH || $x > self::W - self::CORNER_MOUTH || abs($x - self::W / 2) < self::SIDE_MOUTH;
    }

    /** Along a short rail: within a corner pocket's mouth. */
    private static function inShortMouth(float $y): bool
    {
        return $y < self::CORNER_MOUTH || $y > self::H - self::CORNER_MOUTH;
    }

    /**
     * [t ms, x, y, vx, vy] (rounded), plus 1 when the ball dropped into a pocket there.
     *
     * @param  array{0: float, 1: float}  $p
     * @param  array{0: float, 1: float}  $v
     * @return list<float|int>
     */
    private static function segment(int $t, array $p, array $v, bool $potted = false): array
    {
        $segment = [$t, round($p[0], 1), round($p[1], 1), round($v[0], 1), round($v[1], 1)];

        return $potted ? [...$segment, 1] : $segment;
    }
}
