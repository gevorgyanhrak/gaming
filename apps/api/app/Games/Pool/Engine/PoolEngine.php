<?php

namespace App\Games\Pool\Engine;

use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Contracts\HandlesTimeouts;
use App\Gameplay\Contracts\PausesBetweenRounds;
use App\Gameplay\Contracts\ValidatesActions;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\RandomSource;

/**
 * 8-ball for two players. The engine runs the shots (PoolPhysics); clients only send where they aim.
 *
 * A turn is one shot: shoot {angle (degrees), power (1–100)} and, with the cue ball in hand, {x, y} where
 * to put it (anywhere on the table, clear of the other balls). Seat 0 breaks from the head spot. The table
 * is open until a player pots a ball after the break without a foul: that ball's group (solids 1–7,
 * stripes 9–15) is theirs. You shoot again after potting one of yours (open table: any ball) without a foul.
 *
 * Fouls — potting the cue ball, hitting nothing, hitting first a ball that isn't yours (on an open table:
 * the 8), or no ball reaching a cushion after the contact when nothing is potted — give the opponent the
 * cue ball in hand. Potting the 8 wins once your group is cleared, if it isn't a foul; potting it earlier
 * or with a foul loses. An 8 potted on the break is put back on the foot spot.
 *
 * After each shot the table pauses while the balls roll (the shot's length), then the next shot is due.
 *
 * State: balls (id → [x, y] or null when potted), groups (per seat: solids|stripes|null), to_act, next,
 * phase (aim|rolling), ball_in_hand, broken, shot (number), last_shot (shooter, cue start, angle, power,
 * segments, potted, foul, duration_ms), result.
 */
final class PoolEngine implements GameEngine, HandlesTimeouts, PausesBetweenRounds, ValidatesActions
{
    public const SOLIDS = [1, 2, 3, 4, 5, 6, 7];

    public const STRIPES = [9, 10, 11, 12, 13, 14, 15];

    public const HEAD = [635.0, 635.0];

    public const FOOT = [1905.0, 635.0];

    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        return [
            'balls' => self::rack($random),
            'groups' => [null, null],
            'to_act' => 0,
            'next' => null,
            'phase' => 'aim',
            'ball_in_hand' => false,
            'broken' => false,
            'shot' => 0,
            'last_shot' => null,
            'result' => null,
        ];
    }

    /**
     * The fifteen balls in a triangle at the foot spot, the 8 in the middle and a solid and a stripe in the
     * back corners; the cue ball on the head spot.
     *
     * @return array<int, array{0: float, 1: float}|null>
     */
    public static function rack(RandomSource $random): array
    {
        $others = [1, 2, 3, 4, 5, 6, 7, 9, 10, 11, 12, 13, 14, 15];
        for ($i = count($others) - 1; $i > 0; $i--) {
            $j = $random->int(0, $i);
            [$others[$i], $others[$j]] = [$others[$j], $others[$i]];
        }
        // Back corners (row 4, first and last) must be one of each group.
        $solid = array_values(array_filter($others, fn (int $b) => $b < 8))[0];
        $stripe = array_values(array_filter($others, fn (int $b) => $b > 8))[0];
        $rest = array_values(array_diff($others, [$solid, $stripe]));

        $gap = 0.05;
        $balls = [0 => self::HEAD];
        $slot = 0;
        for ($row = 0; $row < 5; $row++) {
            for ($k = 0; $k <= $row; $k++) {
                $x = self::FOOT[0] + $row * (2 * PoolPhysics::R + $gap) * sqrt(3) / 2;
                $y = self::FOOT[1] + ($k - $row / 2) * (2 * PoolPhysics::R + $gap);
                $id = match (true) {
                    $row === 2 && $k === 1 => 8,
                    $row === 4 && $k === 0 => $solid,
                    $row === 4 && $k === 4 => $stripe,
                    default => $rest[$slot++],
                };
                $balls[$id] = [round($x, 2), round($y, 2)];
            }
        }
        ksort($balls);

        return $balls;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function seatsToAct(array $state): array
    {
        return $state['result'] === null && $state['phase'] === 'aim' ? [$state['to_act']] : [];
    }

    /**
     * Shots can't be listed: one action describes them (power range, and whether the cue ball is in hand).
     *
     * @param  array<string, mixed>  $state
     */
    public function legalActions(array $state, int $seat): array
    {
        if ($this->seatsToAct($state) !== [$seat]) {
            return [];
        }

        return [new Action('shoot', ['ball_in_hand' => $state['ball_in_hand'], 'max' => 100, 'min' => 1])];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function isLegal(array $state, int $seat, Action $action): bool
    {
        if ($this->seatsToAct($state) !== [$seat] || $action->type !== 'shoot') {
            return false;
        }
        $p = $action->payload;
        $keys = array_keys($p);
        sort($keys);
        $number = fn (mixed $v) => is_int($v) || is_float($v);
        if ($state['ball_in_hand']) {
            if ($keys !== ['angle', 'power', 'x', 'y'] || ! $number($p['x']) || ! $number($p['y'])
                || ! PoolPhysics::free($state['balls'], (float) $p['x'], (float) $p['y'])) {
                return false;
            }
        } elseif ($keys !== ['angle', 'power']) {
            return false;
        }

        return $number($p['angle']) && $p['angle'] >= -360 && $p['angle'] <= 360
            && $number($p['power']) && $p['power'] >= 1 && $p['power'] <= 100;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition
    {
        if (! $this->isLegal($state, $seat, $action)) {
            throw new IllegalAction('That shot is not allowed.');
        }
        $p = $action->payload;
        $balls = $state['balls'];
        if ($state['ball_in_hand']) {
            $balls[0] = [round((float) $p['x'], 1), round((float) $p['y'], 1)];
        }
        $cueStart = $balls[0];
        $sim = PoolPhysics::shoot($balls, (float) $p['angle'], (float) $p['power']);
        $state['balls'] = $sim['balls'];

        $breaking = ! $state['broken'];
        $group = $state['groups'][$seat];
        $mine = $group === null ? null : ($group === 'solids' ? self::SOLIDS : self::STRIPES);
        $cleared = $mine !== null && array_filter($mine, fn (int $b) => $balls[$b] !== null) === [];
        $potted = $sim['potted'];
        $objects = array_values(array_filter($potted, fn (int $b) => $b !== 0 && $b !== 8));

        $foul = match (true) {
            in_array(0, $potted, true) => 'scratch',
            $sim['first_contact'] === null => 'no_contact',
            $breaking => null,
            $mine === null && $sim['first_contact'] === 8 => 'wrong_ball',
            $mine !== null && ! $cleared && ! in_array($sim['first_contact'], $mine, true) => 'wrong_ball',
            $cleared && $sim['first_contact'] !== 8 => 'wrong_ball',
            $potted === [] && ! $sim['rail_after_contact'] => 'no_rail',
            default => null,
        };

        $events = [['type' => 'shot', 'seat' => $seat, 'potted' => $potted, 'foul' => $foul]];
        $state['last_shot'] = [
            'number' => $state['shot'] + 1,
            'seat' => $seat,
            'cue' => $cueStart,
            'angle' => (float) $p['angle'],
            'power' => (float) $p['power'],
            'segments' => $sim['segments'],
            'potted' => $potted,
            'foul' => $foul,
            'duration_ms' => $sim['duration_ms'],
        ];
        $state['shot']++;
        $state['broken'] = true;
        if ($foul === 'scratch') {
            $state['balls'][0] = null;
        }

        if (in_array(8, $potted, true)) {
            if ($breaking) {
                $state['balls'][8] = self::spot($state['balls'], self::FOOT, except: 8);
            } else {
                $won = $foul === null && $cleared;
                $state['result'] = ['winners' => [$won ? $seat : 1 - $seat], 'reason' => $won ? 'eight_potted' : ($foul !== null ? 'eight_foul' : 'eight_early')];
                $state['phase'] = 'rolling';
                $state['next'] = null;

                return new Transition($state, $events);
            }
        }

        // The first ball potted after the break, without a foul, decides the groups.
        if ($group === null && ! $breaking && $foul === null && $objects !== []) {
            $group = $objects[0] < 8 ? 'solids' : 'stripes';
            $state['groups'][$seat] = $group;
            $state['groups'][1 - $seat] = $group === 'solids' ? 'stripes' : 'solids';
            $events[] = ['type' => 'groups', 'seat' => $seat, 'group' => $group];
        }
        $mineNow = $state['groups'][$seat] === null ? null : ($state['groups'][$seat] === 'solids' ? self::SOLIDS : self::STRIPES);
        $scored = $mineNow === null ? $objects !== [] : array_intersect($objects, $mineNow) !== [];

        $state['next'] = $foul === null && $scored ? $seat : 1 - $seat;
        $state['ball_in_hand'] = $foul !== null;
        $state['phase'] = 'rolling';
        $state['to_act'] = null;

        return new Transition($state, $events);
    }

    /**
     * A timed-out player takes a soft shot at one of their balls (cue ball in hand: from the head spot).
     *
     * @param  array<string, mixed>  $state
     */
    public function timeoutAction(array $state, int $seat): Action
    {
        $cue = $state['ball_in_hand'] ? self::spot($state['balls'], self::HEAD, except: 0) : $state['balls'][0];
        $targets = $this->targets($state, $seat);
        $target = $state['balls'][$targets[0] ?? 8] ?? [PoolPhysics::W / 2, PoolPhysics::H / 2];
        $angle = round(rad2deg(atan2($target[1] - $cue[1], $target[0] - $cue[0])), 2);
        $payload = ['angle' => $angle, 'power' => 40];

        return new Action('shoot', $state['ball_in_hand'] ? [...$payload, 'x' => $cue[0], 'y' => $cue[1]] : $payload);
    }

    /**
     * The balls $seat should hit: their group's left on the table, the 8 once it's cleared, any object
     * ball on an open table.
     *
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    public function targets(array $state, int $seat): array
    {
        $group = $state['groups'][$seat];
        $candidates = $group === null ? [...self::SOLIDS, ...self::STRIPES] : ($group === 'solids' ? self::SOLIDS : self::STRIPES);
        $left = array_values(array_filter($candidates, fn (int $b) => $state['balls'][$b] !== null));

        return $left === [] && $group !== null ? [8] : $left;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function viewFor(array $state, ?int $seat): array
    {
        return [...$state, 'seat' => $seat];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function outcome(array $state): ?Outcome
    {
        return $state['result'] === null ? null : new Outcome($state['result']['winners'], $state['result']['reason']);
    }

    /**
     * While the balls of the last shot roll.
     *
     * @param  array<string, mixed>  $state
     */
    public function pauseMs(array $state): ?int
    {
        return $state['result'] === null && $state['phase'] === 'rolling'
            ? min(14000, (int) ($state['last_shot']['duration_ms'] ?? 0) + 700)
            : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function resume(array $state, RandomSource $random): Transition
    {
        if ($this->pauseMs($state) === null) {
            throw new IllegalAction('There is no pause to end.');
        }
        $state['phase'] = 'aim';
        $state['to_act'] = $state['next'];
        $state['next'] = null;

        return new Transition($state, [['type' => 'turn', 'seat' => $state['to_act']]]);
    }

    /**
     * A free place on the long axis through $spot (towards the foot rail first, then the head).
     *
     * @param  array<int, array{0: float, 1: float}|null>  $balls
     * @param  array{0: float, 1: float}  $spot
     * @param  int  $except  The ball being placed.
     * @return array{0: float, 1: float}
     */
    public static function spot(array $balls, array $spot, int $except): array
    {
        for ($i = 0; $i < 80; $i++) {
            $x = $spot[0] + ($i % 2 === 0 ? 1 : -1) * intdiv($i + 1, 2) * PoolPhysics::R;
            if (PoolPhysics::free($balls, $x, $spot[1], $except)) {
                return [$x, $spot[1]];
            }
        }

        return $spot;
    }
}
