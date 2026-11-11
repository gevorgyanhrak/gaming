<?php

namespace App\Games\Ludo\Engine;

use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Contracts\HandlesDepartures;
use App\Gameplay\Contracts\HandlesTimeouts;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\RandomSource;

/**
 * Ludo for 2–4 players, four tokens each. Two players take opposite colours.
 *
 * A turn: roll (the engine rolls one die), then move {token, to} one of the moves listed. A token leaves
 * its yard only on a 6, to its start square. Tokens go once round the 52-square track (positions 0–50,
 * counted from the colour's start), then up their home column (51–55) and home (56), which takes the
 * exact roll. Landing on an opponent outside a safe square (the four start squares and the four stars)
 * sends their tokens back to the yard; your own tokens share a square. A 6 gives another roll; the third
 * 6 in a row ends the turn without moving. A roll with no move passes the turn. The first player with
 * all four tokens home wins.
 *
 * State: colours (per seat), tokens (per seat: -1 yard, 0–56), out, to_act, phase (roll|move), dice
 * (the last roll), sixes (in a row), last (the last move, for animation), turn, result.
 */
final class LudoEngine implements GameEngine, HandlesDepartures, HandlesTimeouts
{
    public const TRACK = 52;

    public const HOME = 56;

    /** Track positions (from a colour's start) that are safe for everyone: start squares and stars. */
    private const SAFE = [0, 8, 13, 21, 26, 34, 39, 47];

    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        $n = $setup->seats;
        $colours = match ($n) {
            2 => [0, 2],
            3 => [0, 1, 2],
            default => [0, 1, 2, 3],
        };

        return [
            'colours' => $colours,
            'tokens' => array_fill(0, $n, [-1, -1, -1, -1]),
            'out' => array_fill(0, $n, false),
            'to_act' => 0,
            'phase' => 'roll',
            'dice' => null,
            'sixes' => 0,
            'last' => null,
            'turn' => 1,
            'result' => null,
        ];
    }

    /** The square on the shared track (0–51) of a token at $position for $colour, or null off the track. */
    public static function square(int $colour, int $position): ?int
    {
        return $position >= 0 && $position <= 50 ? ($position + 13 * $colour) % self::TRACK : null;
    }

    public static function isSafe(int $square): bool
    {
        return in_array($square, self::SAFE, true);
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
        if ($state['phase'] === 'roll') {
            return [new Action('roll')];
        }

        return array_map(fn (array $m) => new Action('move', ['to' => $m[1], 'token' => $m[0]]), $this->moves($state, $seat, (int) $state['dice']));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition
    {
        if (! collect($this->legalActions($state, $seat))->contains(fn (Action $a) => $a->equals($action))) {
            throw new IllegalAction('That move is not allowed.');
        }

        if ($action->type === 'roll') {
            $dice = $random->int(1, 6);
            $state['dice'] = $dice;
            $state['sixes'] = $dice === 6 ? $state['sixes'] + 1 : 0;
            $events = [['type' => 'roll', 'seat' => $seat, 'dice' => $dice]];
            if ($state['sixes'] === 3) {
                $events[] = ['type' => 'three_sixes', 'seat' => $seat];

                return new Transition($this->nextPlayer($state, $seat), $events);
            }
            if ($this->moves($state, $seat, $dice) === []) {
                $events[] = ['type' => 'no_move', 'seat' => $seat];

                return new Transition($dice === 6 ? [...$state, 'phase' => 'roll'] : $this->nextPlayer($state, $seat), $events);
            }
            $state['phase'] = 'move';

            return new Transition($state, $events);
        }

        $token = (int) $action->payload['token'];
        $to = (int) $action->payload['to'];
        $from = $state['tokens'][$seat][$token];
        $state['tokens'][$seat][$token] = $to;
        $captured = [];
        $square = self::square($state['colours'][$seat], $to);
        if ($square !== null && ! self::isSafe($square)) {
            foreach ($state['tokens'] as $other => $tokens) {
                if ($other === $seat || $state['out'][$other]) {
                    continue;
                }
                foreach ($tokens as $i => $position) {
                    if (self::square($state['colours'][$other], $position) === $square) {
                        $state['tokens'][$other][$i] = -1;
                        $captured[] = ['seat' => $other, 'token' => $i];
                    }
                }
            }
        }
        $state['last'] = ['seat' => $seat, 'token' => $token, 'from' => $from, 'to' => $to, 'captured' => $captured];
        $events = [['type' => 'move', 'seat' => $seat, 'token' => $token, 'from' => $from, 'to' => $to, 'captured' => $captured]];

        if (count(array_filter($state['tokens'][$seat], fn (int $p) => $p === self::HOME)) === 4) {
            $state['to_act'] = null;
            $state['result'] = ['winners' => [$seat], 'reason' => 'all_home'];

            return new Transition($state, $events);
        }

        // A 6 rolls again.
        return new Transition($state['dice'] === 6 ? [...$state, 'phase' => 'roll'] : $this->nextPlayer($state, $seat), $events);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function timeoutAction(array $state, int $seat): ?Action
    {
        return $this->legalActions($state, $seat)[0] ?? null;
    }

    /**
     * The player leaves: their tokens leave the board; with one player left, they win.
     *
     * @param  array<string, mixed>  $state
     */
    public function depart(array $state, int $seat, RandomSource $random): Transition
    {
        $state['out'][$seat] = true;
        $state['tokens'][$seat] = [-1, -1, -1, -1];
        $active = array_keys(array_filter($state['out'], fn (bool $out) => ! $out));
        if (count($active) <= 1) {
            $state['to_act'] = null;
            $state['result'] = ['winners' => $active, 'reason' => 'last_standing'];
        } elseif ($state['to_act'] === $seat) {
            $state = $this->nextPlayer($state, $seat);
        }

        return new Transition($state, [['type' => 'left', 'seat' => $seat]]);
    }

    /**
     * Everything on a ludo board is public.
     *
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
        if ($state['result'] === null) {
            return null;
        }
        $home = array_map(fn (array $tokens) => count(array_filter($tokens, fn (int $p) => $p === self::HOME)), $state['tokens']);

        return new Outcome($state['result']['winners'], $state['result']['reason'], $home);
    }

    /**
     * [token, to] for every token that can move $dice squares.
     *
     * @param  array<string, mixed>  $state
     * @return list<array{0: int, 1: int}>
     */
    private function moves(array $state, int $seat, int $dice): array
    {
        $moves = [];
        foreach ($state['tokens'][$seat] as $token => $position) {
            $to = match (true) {
                $position === -1 => $dice === 6 ? 0 : null,
                $position + $dice <= self::HOME => $position + $dice,
                default => null,
            };
            if ($to !== null && $position !== self::HOME) {
                $moves[] = [$token, $to];
            }
        }

        return $moves;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function nextPlayer(array $state, int $seat): array
    {
        $n = count($state['tokens']);
        $next = $seat;
        do {
            $next = ($next + 1) % $n;
        } while ($state['out'][$next] && $next !== $seat);

        return [...$state, 'to_act' => $next, 'phase' => 'roll', 'sixes' => 0, 'turn' => $state['turn'] + 1];
    }
}
