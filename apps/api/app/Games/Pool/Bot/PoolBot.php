<?php

namespace App\Games\Pool\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Pool\Engine\PoolEngine;
use App\Games\Pool\Engine\PoolPhysics;

/**
 * Finds pots by ghost-ball aiming (hit the target where it would roll into a pocket), ranks them by how
 * thin the cut is and whether the paths are clear, then tries the best few in the same simulation the
 * table uses and plays the one that works out best. Its aim then wobbles: a few degrees (easy), about one
 * (medium), barely (hard). Breaks hard at the rack; with the cue ball in hand it sets up a straight shot.
 */
final class PoolBot implements BotStrategy
{
    private const POCKETS = [[0, 0], [1270, -10], [2540, 0], [0, 1270], [1270, 1280], [2540, 1270]];

    /**
     * @param  array<string, mixed>  $view
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        $engine = new PoolEngine;
        $seat = (int) $view['seat'];
        $balls = $view['balls'];
        $inHand = (bool) $view['ball_in_hand'];
        [$noise, $tries] = match ($difficulty) {
            BotDifficulty::Easy => [3.0, 3],
            BotDifficulty::Medium => [1.0, 6],
            BotDifficulty::Hard => [0.25, 10],
        };

        if (! $view['broken']) {
            // Break: full power at the apex ball on the foot spot.
            $angle = rad2deg(atan2(PoolEngine::FOOT[1] - $balls[0][1], PoolEngine::FOOT[0] - $balls[0][0]));

            return $this->shot($balls[0], $angle + $this->wobble($noise, $random), 100, false);
        }

        $targets = $engine->targets($view, $seat);
        $candidates = [];
        foreach ($targets as $target) {
            foreach (self::POCKETS as $pocket) {
                $candidate = $this->candidate($balls, $target, $pocket, $inHand);
                if ($candidate !== null) {
                    $candidates[] = $candidate;
                }
            }
        }
        usort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $best = null;
        foreach (array_slice($candidates, 0, $tries) as $candidate) {
            foreach ([45, 70] as $power) {
                $trial = $balls;
                $trial[0] = $candidate['cue'];
                $result = PoolPhysics::shoot($trial, $candidate['angle'], $power);
                $value = $this->value($result, $targets, $view, $seat);
                if ($best === null || $value > $best['value']) {
                    $best = [...$candidate, 'power' => $power, 'value' => $value];
                }
            }
        }

        if ($best === null) {
            // Nothing pottable: roll softly into the nearest target.
            $cue = $inHand ? PoolEngine::spot($balls, PoolEngine::HEAD, except: 0) : $balls[0];
            $target = $balls[$targets[0] ?? 8];
            $best = ['cue' => $cue, 'angle' => rad2deg(atan2($target[1] - $cue[1], $target[0] - $cue[0])), 'power' => 35];
        }

        return $this->shot($best['cue'], $best['angle'] + $this->wobble($noise, $random), $best['power'], $inHand);
    }

    /**
     * A ghost-ball shot at $target into $pocket, or null when the cut is too thin or something is in the way.
     *
     * @param  array<int, array{0: float, 1: float}|null>  $balls
     * @param  array{0: int, 1: int}  $pocket
     * @return array{cue: array{0: float, 1: float}, angle: float, score: float}|null
     */
    private function candidate(array $balls, int $target, array $pocket, bool $inHand): ?array
    {
        $t = $balls[$target];
        [$px, $py] = $pocket;
        $toPocket = [$px - $t[0], $py - $t[1]];
        $len = hypot($toPocket[0], $toPocket[1]);
        if ($len < 1) {
            return null;
        }
        $dir = [$toPocket[0] / $len, $toPocket[1] / $len];
        $ghost = [$t[0] - $dir[0] * 2 * PoolPhysics::R, $t[1] - $dir[1] * 2 * PoolPhysics::R];

        $cue = $balls[0];
        if ($inHand) {
            // Put the cue ball on the line, behind the ghost ball.
            $cue = [$ghost[0] - $dir[0] * 300, $ghost[1] - $dir[1] * 300];
            if (! PoolPhysics::free($balls, $cue[0], $cue[1])) {
                return null;
            }
        }
        $aim = [$ghost[0] - $cue[0], $ghost[1] - $cue[1]];
        $aimLen = hypot($aim[0], $aim[1]);
        if ($aimLen < 1) {
            return null;
        }
        $cut = acos(max(-1.0, min(1.0, ($aim[0] * $dir[0] + $aim[1] * $dir[1]) / $aimLen)));
        if ($cut > deg2rad(70)) {
            return null;
        }
        foreach ($balls as $id => $ball) {
            if ($ball === null || $id === 0 || $id === $target) {
                continue;
            }
            if ($this->blocks($cue, $ghost, $ball) || $this->blocks($t, [(float) $px, (float) $py], $ball)) {
                return null;
            }
        }

        return [
            'cue' => [round($cue[0], 1), round($cue[1], 1)],
            'angle' => rad2deg(atan2($aim[1], $aim[0])),
            'score' => -$cut * 3 - ($aimLen + $len) / 1000,
        ];
    }

    /**
     * Whether $ball sits within a ball's width of the path from $a to $b.
     *
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     * @param  array{0: float, 1: float}  $ball
     */
    private function blocks(array $a, array $b, array $ball): bool
    {
        [$dx, $dy] = [$b[0] - $a[0], $b[1] - $a[1]];
        $len2 = $dx * $dx + $dy * $dy;
        if ($len2 === 0.0) {
            return false;
        }
        $t = max(0.0, min(1.0, (($ball[0] - $a[0]) * $dx + ($ball[1] - $a[1]) * $dy) / $len2));
        $cx = $a[0] + $t * $dx - $ball[0];
        $cy = $a[1] + $t * $dy - $ball[1];

        return $cx * $cx + $cy * $cy < (2 * PoolPhysics::R) ** 2 && $t > 0.02 && $t < 0.98;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<int>  $targets
     * @param  array<string, mixed>  $view
     */
    private function value(array $result, array $targets, array $view, int $seat): float
    {
        $potted = $result['potted'];
        if (in_array(8, $potted, true)) {
            return $targets === [8] && ! in_array(0, $potted, true) ? 10000 : -10000;
        }
        $value = 0.0;
        if (in_array(0, $potted, true)) {
            $value -= 500;
        }
        if ($result['first_contact'] === null || ! in_array($result['first_contact'], $targets, true)) {
            $value -= 400;
        }
        $mine = count(array_intersect($potted, $targets));
        $theirs = count(array_diff($potted, $targets, [0]));

        return $value + $mine * 300 - $theirs * 80;
    }

    private function wobble(float $degrees, RandomSource $random): float
    {
        return ($random->int(-1000, 1000) / 1000) * $degrees;
    }

    /**
     * @param  array{0: float, 1: float}  $cue
     */
    private function shot(array $cue, float $angle, int $power, bool $inHand): Action
    {
        $payload = ['angle' => round($angle, 2), 'power' => $power];

        return new Action('shoot', $inHand ? [...$payload, 'x' => $cue[0], 'y' => $cue[1]] : $payload);
    }
}
