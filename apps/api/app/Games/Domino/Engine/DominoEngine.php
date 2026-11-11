<?php

namespace App\Games\Domino\Engine;

use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Contracts\HandlesDepartures;
use App\Gameplay\Contracts\HandlesTimeouts;
use App\Gameplay\Contracts\PausesBetweenRounds;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\RandomSource;

/**
 * Block dominoes for 2–4 players with a double-six set, to `target` points (100).
 *
 * Each round everyone gets seven tiles; the rest stay face down and are never drawn. In the first round
 * the holder of the highest double leads it (without a double dealt, the heaviest tile); later rounds are
 * led by the last round's winner (after a drawn round, by the next player), with any tile. Players in turn
 * add a tile to either end of the line, matching the number there: play {tile: "a-b", end: left|right}.
 * A player who can't play passes; the engine passes for them. The round ends when a player plays their last
 * tile (they score the pips left in everyone else's hand) or when nobody can play (blocked: the lowest
 * pip count scores the others' pips; a tie for lowest scores nothing). After each round the table pauses;
 * the first to reach the target with the highest score wins.
 *
 * State: target, scores, out (left the game), round, phase (playing|over), hands (hidden), line (tiles
 * as [a, b] in order, each with the seat that played it), to_act, leader, last_round (winner, points,
 * blocked, hands shown), pause_ms, result. A tile is "a-b" with a ≤ b.
 */
final class DominoEngine implements GameEngine, HandlesDepartures, HandlesTimeouts, PausesBetweenRounds
{
    private const HAND = 7;

    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        $n = $setup->seats;
        $state = [
            'target' => (int) ($setup->options['target'] ?? 100),
            'scores' => array_fill(0, $n, 0),
            'out' => array_fill(0, $n, false),
            'round' => 0,
            'leader' => null,
            'last_round' => null,
            'pause_ms' => (int) ($setup->options['round_pause_ms'] ?? 4000),
            'result' => null,
        ];

        return $this->deal($state, $random);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function seatsToAct(array $state): array
    {
        return $state['result'] === null && $state['to_act'] !== null ? [$state['to_act']] : [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function legalActions(array $state, int $seat): array
    {
        if ($this->seatsToAct($state) !== [$seat]) {
            return [];
        }
        $actions = [];
        foreach ($this->moves($state, $seat) as [$tile, $end]) {
            $actions[] = new Action('play', ['end' => $end, 'tile' => $tile]);
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition
    {
        $legal = collect($this->legalActions($state, $seat))->contains(fn (Action $a) => $a->equals($action));
        if (! $legal) {
            throw new IllegalAction('That move is not allowed.');
        }

        $tile = (string) $action->payload['tile'];
        $end = (string) $action->payload['end'];
        [$a, $b] = self::pips($tile);
        $state['hands'][$seat] = array_values(array_diff($state['hands'][$seat], [$tile]));
        if ($state['line'] === []) {
            $state['line'][] = ['tile' => [$a, $b], 'seat' => $seat];
        } elseif ($end === 'left') {
            $left = $state['line'][0]['tile'][0];
            array_unshift($state['line'], ['tile' => $b === $left ? [$a, $b] : [$b, $a], 'seat' => $seat]);
        } else {
            $right = $state['line'][count($state['line']) - 1]['tile'][1];
            $state['line'][] = ['tile' => $a === $right ? [$a, $b] : [$b, $a], 'seat' => $seat];
        }
        $events = [['type' => 'play', 'seat' => $seat, 'tile' => $tile, 'end' => $end]];

        if ($state['hands'][$seat] === []) {
            return new Transition($this->finishRound($state, $seat, blocked: false, events: $events), $events);
        }

        return new Transition($this->advance($state, $seat, $events), $events);
    }

    /**
     * A timed-out player plays their first legal tile.
     *
     * @param  array<string, mixed>  $state
     */
    public function timeoutAction(array $state, int $seat): ?Action
    {
        return $this->legalActions($state, $seat)[0] ?? null;
    }

    /**
     * The player leaves: their tiles leave the game; with one player left, they win.
     *
     * @param  array<string, mixed>  $state
     */
    public function depart(array $state, int $seat, RandomSource $random): Transition
    {
        $events = [['type' => 'left', 'seat' => $seat]];
        $state['out'][$seat] = true;
        $state['hands'][$seat] = [];
        $active = $this->active($state);
        if (count($active) <= 1) {
            $state['to_act'] = null;
            $state['result'] = ['winners' => $active, 'reason' => 'last_standing'];

            return new Transition($state, $events);
        }
        if ($state['phase'] === 'playing' && $state['to_act'] === $seat) {
            $state = $this->advance($state, $seat, $events);
        }

        return new Transition($state, $events);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function viewFor(array $state, ?int $seat): array
    {
        $view = $state;
        unset($view['hands'], $view['pause_ms']);
        $view['seat'] = $seat;
        $view['hand'] = $seat === null ? [] : $state['hands'][$seat];
        $view['hand_sizes'] = array_map('count', $state['hands']);
        $view['ends'] = $this->ends($state);
        $view['pips_left'] = $seat === null ? null : $this->pipCount($state['hands'][$seat]);

        return $view;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function outcome(array $state): ?Outcome
    {
        return $state['result'] === null ? null : new Outcome($state['result']['winners'], $state['result']['reason'], $state['scores']);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function pauseMs(array $state): ?int
    {
        return $state['result'] === null && $state['phase'] === 'over' ? $state['pause_ms'] : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function resume(array $state, RandomSource $random): Transition
    {
        if ($this->pauseMs($state) === null) {
            throw new IllegalAction('There is no pause to end.');
        }
        $state = $this->deal($state, $random);

        return new Transition($state, [['type' => 'next_round', 'round' => $state['round']]]);
    }

    /**
     * "3-5" → [3, 5].
     *
     * @return array{0: int, 1: int}
     */
    public static function pips(string $tile): array
    {
        [$a, $b] = explode('-', $tile);

        return [(int) $a, (int) $b];
    }

    /**
     * Every legal [tile, end] for $seat; the first tile of a round must be the required lead, if any.
     *
     * @param  array<string, mixed>  $state
     * @return list<array{0: string, 1: string}>
     */
    private function moves(array $state, int $seat): array
    {
        if ($state['line'] === []) {
            $tiles = $state['lead_tile'] !== null ? [$state['lead_tile']] : $state['hands'][$seat];

            return array_map(fn (string $t) => [$t, 'left'], array_values(array_intersect($tiles, $state['hands'][$seat])));
        }
        [$left, $right] = $this->ends($state);
        $moves = [];
        foreach ($state['hands'][$seat] as $tile) {
            [$a, $b] = self::pips($tile);
            if ($a === $left || $b === $left) {
                $moves[] = [$tile, 'left'];
            }
            if ($a === $right || $b === $right) {
                $moves[] = [$tile, 'right'];
            }
        }

        return $moves;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{0: int, 1: int}|null
     */
    private function ends(array $state): ?array
    {
        if ($state['line'] === []) {
            return null;
        }

        return [$state['line'][0]['tile'][0], $state['line'][count($state['line']) - 1]['tile'][1]];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    private function active(array $state): array
    {
        return array_keys(array_filter($state['out'], fn (bool $out) => ! $out));
    }

    /**
     * @param  list<string>  $tiles
     */
    private function pipCount(array $tiles): int
    {
        return array_sum(array_map(fn (string $t) => array_sum(self::pips($t)), $tiles));
    }

    /**
     * The next player who can play gets the turn; those who can't pass. Nobody can: the round is blocked.
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function advance(array $state, int $seat, array &$events): array
    {
        $n = count($state['scores']);
        for ($i = 1; $i <= $n; $i++) {
            $next = ($seat + $i) % $n;
            if ($state['out'][$next]) {
                continue;
            }
            if ($this->moves($state, $next) !== []) {
                $state['to_act'] = $next;

                return $state;
            }
            $events[] = ['type' => 'pass', 'seat' => $next];
        }

        return $this->finishRound($state, null, blocked: true, events: $events);
    }

    /**
     * Score the round; pause before the next one, or end the game.
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function finishRound(array $state, ?int $winner, bool $blocked, array &$events): array
    {
        $active = $this->active($state);
        $pips = [];
        foreach ($active as $seat) {
            $pips[$seat] = $this->pipCount($state['hands'][$seat]);
        }
        if ($blocked) {
            $lowest = min($pips);
            $holders = array_keys(array_filter($pips, fn (int $p) => $p === $lowest));
            $winner = count($holders) === 1 ? $holders[0] : null;
        }
        $points = $winner === null ? 0 : array_sum($pips) - $pips[$winner];
        if ($winner !== null) {
            $state['scores'][$winner] += $points;
        }

        $state['last_round'] = [
            'number' => $state['round'],
            'winner' => $winner,
            'points' => $points,
            'blocked' => $blocked,
            'pips' => $pips,
            'hands' => $state['hands'],
        ];
        $events[] = ['type' => 'round_over', 'winner' => $winner, 'points' => $points, 'blocked' => $blocked];
        $state['phase'] = 'over';
        $state['to_act'] = null;
        $state['leader'] = $winner ?? (($state['leader'] ?? 0) + 1) % count($state['scores']);

        $best = max(array_map(fn (int $s) => $state['scores'][$s], $active));
        $top = array_values(array_filter($active, fn (int $s) => $state['scores'][$s] === $best));
        if ($best >= $state['target'] && count($top) === 1) {
            $state['result'] = ['winners' => $top, 'reason' => 'target_reached'];
        }

        return $state;
    }

    /**
     * Shuffle the 28 tiles and deal seven each; pick who leads and with what.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function deal(array $state, RandomSource $random): array
    {
        $tiles = [];
        for ($a = 0; $a <= 6; $a++) {
            for ($b = $a; $b <= 6; $b++) {
                $tiles[] = "{$a}-{$b}";
            }
        }
        for ($i = count($tiles) - 1; $i > 0; $i--) {
            $j = $random->int(0, $i);
            [$tiles[$i], $tiles[$j]] = [$tiles[$j], $tiles[$i]];
        }

        $n = count($state['scores']);
        $state['round']++;
        $state['phase'] = 'playing';
        $state['line'] = [];
        $state['hands'] = array_map(fn (int $s) => $state['out'][$s] ? [] : array_slice($tiles, $s * self::HAND, self::HAND), range(0, $n - 1));
        $state['lead_tile'] = null;

        $leader = $state['leader'];
        if ($leader === null) {
            // First round: the highest double leads; without a double dealt, the heaviest tile.
            $rank = fn (string $t) => (self::pips($t)[0] === self::pips($t)[1] ? 100 : 0) + array_sum(self::pips($t)) * 2 + self::pips($t)[1];
            $best = null;
            foreach ($state['hands'] as $seat => $hand) {
                foreach ($hand as $tile) {
                    if ($best === null || $rank($tile) > $rank($best[1])) {
                        $best = [$seat, $tile];
                    }
                }
            }
            [$leader, $state['lead_tile']] = $best;
        }
        while ($state['out'][$leader]) {
            $leader = ($leader + 1) % $n;
        }
        $state['leader'] = $leader;
        $state['to_act'] = $leader;

        return $state;
    }
}
