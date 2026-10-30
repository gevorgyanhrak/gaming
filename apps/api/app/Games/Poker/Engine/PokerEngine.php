<?php

namespace App\Games\Poker\Engine;

use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Contracts\HandlesDepartures;
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
 * No-limit Texas hold'em as a sit-and-go for 2–10 seats with play chips: everyone starts with the
 * same stack, blinds double every `level_hands` hands, and hands are dealt until one player has all
 * the chips. Chips exist only inside this game; they have no value and never leave it.
 *
 * Actions of the seat to act: fold, check, call, and raise {amount: the total bet this street}.
 * legalActions() describes raises as {type: raise, min, max}; any amount in between is accepted,
 * and max (all-in) always is. A raise short of the minimum (all-in) doesn't reopen the betting for
 * players who already acted, but they must still call or fold. Hands start, streets are dealt and
 * pots (with side pots) are paid out by the engine; a timed-out player checks or folds. After each
 * hand the table pauses (street "over", nobody to act) so everyone sees who won: pause_ms[0] after
 * a fold win, pause_ms[1] after a showdown; then resume() deals the next hand.
 *
 * State: stacks, out (busted or left), button, hand (number), blinds [sb, bb], deck and hole
 * (hidden), board, street, bets (this street), committed (this hand), folded, acted, current_bet,
 * min_raise, to_act, last_hand (the last finished hand's board, showdown, pots and per-seat
 * payouts), forfeited (chips of players who left), pause_ms, result.
 */
final class PokerEngine implements GameEngine, HandlesDepartures, HandlesTimeouts, PausesBetweenRounds, ValidatesActions
{
    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        $seats = $setup->seats;
        $options = $setup->options;

        $state = [
            'stacks' => array_fill(0, $seats, (int) ($options['starting_chips'] ?? 1000)),
            'out' => array_fill(0, $seats, false),
            'button' => $random->int(0, $seats - 1),
            'hand' => 0,
            'base_blinds' => [(int) ($options['small_blind'] ?? 5), (int) ($options['big_blind'] ?? 10)],
            'level_hands' => (int) ($options['level_hands'] ?? 10),
            'blinds' => [0, 0],
            'deck' => [],
            'hole' => array_fill(0, $seats, []),
            'board' => [],
            'street' => 'preflop',
            'bets' => array_fill(0, $seats, 0),
            'committed' => array_fill(0, $seats, 0),
            'folded' => array_fill(0, $seats, false),
            'acted' => array_fill(0, $seats, false),
            'current_bet' => 0,
            'min_raise' => 0,
            'to_act' => null,
            'last_hand' => null,
            'busted' => [],
            'forfeited' => 0,
            'pause_ms' => [(int) ($options['fold_pause_ms'] ?? 3000), (int) ($options['showdown_pause_ms'] ?? 5000)],
            'result' => null,
        ];

        return $this->startHand($state, $random, moveButton: false);
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

        $owe = $state['current_bet'] - $state['bets'][$seat];
        $actions = $owe > 0 ? [new Action('fold'), new Action('call')] : [new Action('check')];

        $raise = $this->raiseRange($state, $seat);
        if ($raise !== null) {
            $actions[] = new Action('raise', ['max' => $raise[1], 'min' => $raise[0]]);
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function isLegal(array $state, int $seat, Action $action): bool
    {
        if ($this->seatsToAct($state) !== [$seat]) {
            return false;
        }
        if ($action->type === 'raise') {
            $range = $this->raiseRange($state, $seat);
            $amount = $action->payload['amount'] ?? null;

            return $range !== null && is_int($amount) && array_keys($action->payload) === ['amount'] && $amount >= $range[0] && $amount <= $range[1];
        }

        return collect($this->legalActions($state, $seat))->contains(fn (Action $legal) => $legal->equals($action));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition
    {
        if (! $this->isLegal($state, $seat, $action)) {
            throw new IllegalAction('That move is not allowed.');
        }

        $event = ['type' => $action->type, 'seat' => $seat];
        switch ($action->type) {
            case 'fold':
                $state['folded'][$seat] = true;
                break;
            case 'call':
                $event['amount'] = $this->put($state, $seat, $state['current_bet'] - $state['bets'][$seat]);
                break;
            case 'raise':
                $to = (int) $action->payload['amount'];
                $this->put($state, $seat, $to - $state['bets'][$seat]);
                $raisedBy = $to - $state['current_bet'];
                if ($raisedBy >= $state['min_raise']) {
                    // A full raise reopens the betting for everyone else.
                    $state['min_raise'] = $raisedBy;
                    foreach ($state['acted'] as $other => $_) {
                        $state['acted'][$other] = false;
                    }
                }
                $state['current_bet'] = max($state['current_bet'], $to);
                $event['amount'] = $to;
                break;
        }
        $state['acted'][$seat] = true;

        $events = [$event];
        $state = $this->advance($state, $seat, $random, $events);

        return new Transition($state, $events);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function timeoutAction(array $state, int $seat): Action
    {
        return $state['current_bet'] > $state['bets'][$seat] ? new Action('fold') : new Action('check');
    }

    /**
     * The player leaves the table: their hand is folded and their chips leave play.
     *
     * @param  array<string, mixed>  $state
     */
    public function depart(array $state, int $seat, RandomSource $random): Transition
    {
        $events = [['type' => 'left', 'seat' => $seat]];
        $wasToAct = $state['to_act'] === $seat;
        $state['folded'][$seat] = true;
        $state['out'][$seat] = true;
        $state['forfeited'] += $state['stacks'][$seat];
        $state['stacks'][$seat] = 0;
        $state['busted'][] = $seat;

        if ($state['street'] === 'over') {
            // Between hands: nothing to fold into; the game ends if only one player is left.
            $active = array_keys(array_filter($state['out'], fn (bool $out) => ! $out));
            if (count($active) <= 1) {
                $state['result'] = ['winners' => $active, 'reason' => 'last_standing'];
            }

            return new Transition($state, $events);
        }

        if ($state['result'] === null && ($wasToAct || count($this->inHand($state)) <= 1)) {
            $state = $this->advance($state, $seat, $random, $events);
        }

        return new Transition($state, $events);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function viewFor(array $state, ?int $seat): array
    {
        $view = $state;
        unset($view['deck'], $view['hole'], $view['base_blinds'], $view['level_hands'], $view['pause_ms']);
        $view['hand_cards'] = $seat === null ? [] : $state['hole'][$seat];
        $view['pot'] = array_sum($state['committed']);

        return $view;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function outcome(array $state): ?Outcome
    {
        return $state['result'] === null ? null : new Outcome($state['result']['winners'], $state['result']['reason']);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function pauseMs(array $state): ?int
    {
        if ($state['result'] !== null || $state['street'] !== 'over') {
            return null;
        }
        [$fold, $showdown] = $state['pause_ms'] ?? [3000, 5000];

        return ($state['last_hand']['shown'] ?? []) === [] ? $fold : $showdown;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function resume(array $state, RandomSource $random): Transition
    {
        if ($this->pauseMs($state) === null) {
            throw new IllegalAction('There is no pause to end.');
        }
        $state = $this->startHand($state, $random, moveButton: true);

        return new Transition($state, [['type' => 'next_hand', 'hand' => $state['hand']]]);
    }

    /**
     * Raise "to" amounts for $seat: [min, max], or null when it can't raise.
     *
     * @param  array<string, mixed>  $state
     * @return array{0: int, 1: int}|null
     */
    private function raiseRange(array $state, int $seat): ?array
    {
        $owe = $state['current_bet'] - $state['bets'][$seat];
        $max = $state['bets'][$seat] + $state['stacks'][$seat];
        $othersCanAct = array_filter($this->canAct($state), fn (int $s) => $s !== $seat) !== [];

        // Nothing beyond a call, or nobody left to raise against (everyone else is all-in).
        if ($state['stacks'][$seat] <= $owe || ! $othersCanAct) {
            return null;
        }
        $min = $state['current_bet'] === 0 ? $state['blinds'][1] : $state['current_bet'] + $state['min_raise'];

        return [min($min, $max), $max];
    }

    /**
     * Move chips from a stack into the pot (at most the whole stack).
     *
     * @param  array<string, mixed>  $state
     */
    private function put(array &$state, int $seat, int $amount): int
    {
        $paid = max(0, min($amount, $state['stacks'][$seat]));
        $state['stacks'][$seat] -= $paid;
        $state['bets'][$seat] += $paid;
        $state['committed'][$seat] += $paid;

        return $paid;
    }

    /**
     * After $seat acted: next player, next street, or the end of the hand.
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function advance(array $state, int $seat, RandomSource $random, array &$events): array
    {
        $inHand = $this->inHand($state);
        if (count($inHand) === 1) {
            return $this->finishHand($this->payUncontested($state, $inHand[0]), $random, $events);
        }

        $n = count($state['stacks']);
        for ($i = 1; $i <= $n; $i++) {
            $next = ($seat + $i) % $n;
            if (in_array($next, $this->canAct($state), true) && (! $state['acted'][$next] || $state['bets'][$next] < $state['current_bet'])) {
                $state['to_act'] = $next;

                return $state;
            }
        }

        return $this->nextStreet($state, $random, $events);
    }

    /**
     * Deal the next street, or run the board out to showdown when fewer than two can still bet.
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function nextStreet(array $state, RandomSource $random, array &$events): array
    {
        while (true) {
            if ($state['street'] === 'river') {
                return $this->finishHand($this->showdown($state), $random, $events);
            }

            [$street, $count] = match ($state['street']) {
                'preflop' => ['flop', 3],
                'flop' => ['turn', 1],
                default => ['river', 1],
            };
            array_shift($state['deck']);    // burn
            array_push($state['board'], ...array_splice($state['deck'], 0, $count));
            $state['street'] = $street;
            $state['bets'] = array_fill(0, count($state['stacks']), 0);
            $state['acted'] = array_fill(0, count($state['stacks']), false);
            $state['current_bet'] = 0;
            $state['min_raise'] = $state['blinds'][1];
            $events[] = ['type' => 'dealt', 'street' => $street, 'cards' => array_slice($state['board'], -$count)];

            $actors = $this->canAct($state);
            if (count($actors) >= 2) {
                $state['to_act'] = $this->nextFrom($state, $state['button'], $actors);

                return $state;
            }
        }
    }

    /**
     * Split every pot (main and side pots) between the best hands that are eligible for it.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function showdown(array $state): array
    {
        $contenders = $this->inHand($state);
        $scores = [];
        $shown = [];
        foreach ($contenders as $seat) {
            $scores[$seat] = PokerHands::best([...$state['hole'][$seat], ...$state['board']]);
            $shown[$seat] = $state['hole'][$seat];
        }

        $pots = [];
        $payouts = array_fill(0, count($state['stacks']), 0);
        $levels = array_values(array_unique(array_filter($state['committed'])));
        sort($levels);
        $previous = 0;
        foreach ($levels as $level) {
            $amount = 0;
            foreach ($state['committed'] as $committed) {
                $amount += max(0, min($committed, $level) - $previous);
            }
            $eligible = array_values(array_filter($contenders, fn (int $s) => $state['committed'][$s] >= $level));
            $previous = $level;
            if ($amount === 0) {
                continue;
            }
            if ($eligible === []) {
                // Only folded players put this much in: it goes to the best remaining hands.
                $eligible = $contenders;
            }

            $top = max(array_map(fn (int $s) => $scores[$s][0], $eligible));
            $winners = array_values(array_filter($eligible, fn (int $s) => $scores[$s][0] === $top));
            foreach ($this->pay($state, $winners, $amount) as $winner => $share) {
                $payouts[$winner] += $share;
            }
            $pots[] = ['amount' => $amount, 'winners' => $winners, 'hand' => $scores[$winners[0]][1]];
        }

        $state['last_hand'] = ['number' => $state['hand'], 'board' => $state['board'], 'shown' => $shown, 'pots' => $this->mergePots($pots), 'payouts' => $payouts];

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function payUncontested(array $state, int $winner): array
    {
        $pot = array_sum($state['committed']);
        $state['stacks'][$winner] += $pot;
        $payouts = array_fill(0, count($state['stacks']), 0);
        $payouts[$winner] = $pot;
        $state['last_hand'] = ['number' => $state['hand'], 'board' => $state['board'], 'shown' => [], 'pots' => [['amount' => $pot, 'winners' => [$winner], 'hand' => null]], 'payouts' => $payouts];

        return $state;
    }

    /**
     * Split $amount equally; odd chips go to the winners closest to the left of the button.
     *
     * @param  array<string, mixed>  $state
     * @param  list<int>  $winners
     * @return array<int, int> chips paid per seat
     */
    private function pay(array &$state, array $winners, int $amount): array
    {
        $n = count($state['stacks']);
        usort($winners, fn (int $a, int $b) => (($a - $state['button'] - 1 + $n) % $n) <=> (($b - $state['button'] - 1 + $n) % $n));
        $share = intdiv($amount, count($winners));
        $paid = [];
        foreach ($winners as $i => $seat) {
            $paid[$seat] = $share + ($i < $amount % count($winners) ? 1 : 0);
            $state['stacks'][$seat] += $paid[$seat];
        }

        return $paid;
    }

    /**
     * Pots won by the same players are shown as one.
     *
     * @param  list<array{amount: int, winners: list<int>, hand: string}>  $pots
     * @return list<array{amount: int, winners: list<int>, hand: string}>
     */
    private function mergePots(array $pots): array
    {
        $merged = [];
        foreach ($pots as $pot) {
            $last = count($merged) - 1;
            if ($last >= 0 && $merged[$last]['winners'] === $pot['winners']) {
                $merged[$last]['amount'] += $pot['amount'];
            } else {
                $merged[] = $pot;
            }
        }

        return $merged;
    }

    /**
     * Bust players without chips; end the game, or pause before the next hand (see resume()).
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function finishHand(array $state, RandomSource $random, array &$events): array
    {
        $events[] = ['type' => 'hand_over', 'hand' => $state['hand'], 'pots' => $state['last_hand']['pots'] ?? []];
        foreach ($state['stacks'] as $seat => $stack) {
            if ($stack === 0 && ! $state['out'][$seat]) {
                $state['out'][$seat] = true;
                $state['busted'][] = $seat;
            }
        }
        $state['committed'] = array_fill(0, count($state['stacks']), 0);
        $state['bets'] = array_fill(0, count($state['stacks']), 0);

        if (count(array_filter($state['out'], fn (bool $out) => ! $out)) <= 1) {
            return $this->startHand($state, $random, moveButton: true);    // ends the game
        }
        $state['street'] = 'over';
        $state['to_act'] = null;

        return $state;
    }

    /**
     * Shuffle, deal, post blinds and give the first player the action.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function startHand(array $state, RandomSource $random, bool $moveButton): array
    {
        $n = count($state['stacks']);
        $active = array_keys(array_filter($state['out'], fn (bool $out) => ! $out));
        if (count($active) <= 1) {
            $state['to_act'] = null;
            $state['result'] = ['winners' => $active, 'reason' => 'last_standing'];

            return $state;
        }
        if ($moveButton || $state['out'][$state['button']]) {
            $state['button'] = $this->nextFrom($state, $state['button'], $active);
        }
        $state['hand']++;
        $level = intdiv($state['hand'] - 1, max(1, $state['level_hands']));
        $state['blinds'] = [$state['base_blinds'][0] * 2 ** $level, $state['base_blinds'][1] * 2 ** $level];

        $state['deck'] = PokerHands::shuffle(PokerHands::deck(), $random);
        $state['hole'] = array_fill(0, $n, []);
        foreach ($active as $seat) {
            $state['hole'][$seat] = array_splice($state['deck'], 0, 2);
        }
        $state['board'] = [];
        $state['street'] = 'preflop';
        $state['bets'] = array_fill(0, $n, 0);
        $state['committed'] = array_fill(0, $n, 0);
        $state['folded'] = $state['out'];
        $state['acted'] = array_fill(0, $n, false);

        // Heads-up the button posts the small blind and acts first before the flop.
        [$small, $big] = count($active) === 2
            ? [$state['button'], $this->nextFrom($state, $state['button'], $active)]
            : [$this->nextFrom($state, $state['button'], $active), $this->nextFrom($state, $this->nextFrom($state, $state['button'], $active), $active)];
        $this->put($state, $small, $state['blinds'][0]);
        $this->put($state, $big, $state['blinds'][1]);
        $state['current_bet'] = max($state['bets']);
        $state['min_raise'] = $state['blinds'][1];

        $actors = $this->canAct($state);
        $first = $actors === [] ? null : $this->nextFrom($state, $big, $actors);
        if ($first === null || (count($actors) === 1 && $state['bets'][$first] >= $state['current_bet'])) {
            $events = [];

            return $this->nextStreet($state, $random, $events);
        }
        $state['to_act'] = $first;

        return $state;
    }

    /**
     * Seats still in the hand.
     *
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    private function inHand(array $state): array
    {
        return array_keys(array_filter($state['folded'], fn (bool $folded) => ! $folded));
    }

    /**
     * Seats in the hand with chips behind (not all-in).
     *
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    private function canAct(array $state): array
    {
        return array_values(array_filter($this->inHand($state), fn (int $seat) => $state['stacks'][$seat] > 0));
    }

    /**
     * The first of $seats after $seat, going round the table.
     *
     * @param  array<string, mixed>  $state
     * @param  list<int>  $seats
     */
    private function nextFrom(array $state, int $seat, array $seats): int
    {
        $n = count($state['stacks']);
        for ($i = 1; $i <= $n; $i++) {
            if (in_array(($seat + $i) % $n, $seats, true)) {
                return ($seat + $i) % $n;
            }
        }

        return $seats[0];
    }
}
