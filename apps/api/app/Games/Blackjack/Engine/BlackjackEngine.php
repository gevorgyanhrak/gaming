<?php

namespace App\Games\Blackjack\Engine;

use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Contracts\HandlesDepartures;
use App\Gameplay\Contracts\HandlesTimeouts;
use App\Gameplay\Contracts\PausesBetweenRounds;
use App\Gameplay\Contracts\PlaysForChips;
use App\Gameplay\Contracts\ValidatesActions;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\RandomSource;

/**
 * Blackjack against the house dealer for 1–5 seats, played for a fixed number of rounds. The chips are
 * the players' own Fishkas (PlaysForChips): each person starts with their buy-in (`options.stacks`).
 *
 * A round: every seat in turn bets {amount} between the table's limits (`table_min_bet`, `table_max_bet`
 * from the play group; at most the seat's stack). Then two cards each and two for the dealer, one face
 * down. With an ace or a ten showing the dealer peeks: a dealer blackjack ends the round at once (a
 * player's blackjack pushes). Otherwise each seat plays its hands in turn: hit, stand, double (first two
 * cards, one more card, bet doubled) or split (two cards of the same value, up to four hands; split aces
 * get one card each; 21 after a split is not a blackjack). The dealer then draws to 17 (stands on soft
 * 17 unless `dealer_hits_soft_17`). Wins pay 1:1, a blackjack 3:2 (rounded down), a push returns the bet.
 * No insurance or surrender. The shoe of `decks` decks is reshuffled when a quarter is left.
 *
 * After each round the table pauses (`pause_ms`) to show the result. The game ends after `rounds`
 * rounds, or when nobody has chips left. Seats that finish ahead of their buy-in win; when nobody does,
 * the house (Outcome::HOUSE) wins. A player who leaves mid-round forfeits the bets in play.
 *
 * State: stacks, start (buy-ins), out, round, rounds, limits [min, max], decks, h17, phase
 * (betting|playing|over), shoe (hidden), cut, bets (betting phase), hands per seat (cards, bet, doubled,
 * split, status playing|stood|bust|blackjack, result win|lose|push|blackjack|null, payout), dealer cards,
 * hole_hidden, to_act, active (hand index), last_round (number, dealer, dealer_total, net per seat),
 * pause_ms, result.
 */
final class BlackjackEngine implements GameEngine, HandlesDepartures, HandlesTimeouts, PausesBetweenRounds, PlaysForChips, ValidatesActions
{
    /** Hands a seat may hold after splitting. */
    private const MAX_HANDS = 4;

    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        $seats = $setup->seats;
        $options = $setup->options;
        $default = $this->buyIn($options);
        $stacks = array_map(fn (int $seat) => (int) ($options['stacks'][$seat] ?? $default), range(0, $seats - 1));
        $min = max(1, (int) ($options['table_min_bet'] ?? $options['min_bet'] ?? 10));
        $max = (int) ($options['table_max_bet'] ?? 0) ?: $min * (int) ($options['max_bet_multiple'] ?? 100);
        $decks = max(1, min(8, (int) ($options['decks'] ?? 6)));

        $state = [
            'stacks' => $stacks,
            'start' => $stacks,
            'out' => array_fill(0, $seats, false),
            'round' => 0,
            'rounds' => max(1, (int) ($options['rounds'] ?? 10)),
            'limits' => [$min, max($min, $max)],
            'decks' => $decks,
            'h17' => (bool) ($options['dealer_hits_soft_17'] ?? false),
            'phase' => 'betting',
            'shoe' => [],
            'cut' => intdiv($decks * 52, 4),
            'bets' => array_fill(0, $seats, 0),
            'hands' => array_fill(0, $seats, []),
            'dealer' => [],
            'hole_hidden' => false,
            'to_act' => null,
            'active' => 0,
            'last_round' => null,
            'pause_ms' => (int) ($options['round_pause_ms'] ?? 3500),
            'result' => null,
        ];

        return $this->startRound($state);
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
        if ($state['phase'] === 'betting') {
            [$min, $max] = $this->betRange($state, $seat);

            return [new Action('bet', ['max' => $max, 'min' => $min])];
        }

        $hand = $state['hands'][$seat][$state['active']];
        $actions = [new Action('hit'), new Action('stand')];
        $affords = $state['stacks'][$seat] >= $hand['bet'];
        if (count($hand['cards']) === 2 && $affords) {
            $actions[] = new Action('double');
            if ($this->canSplit($state, $seat, $hand)) {
                $actions[] = new Action('split');
            }
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
        if ($action->type === 'bet') {
            if ($state['phase'] !== 'betting') {
                return false;
            }
            [$min, $max] = $this->betRange($state, $seat);
            $amount = $action->payload['amount'] ?? null;

            return is_int($amount) && array_keys($action->payload) === ['amount'] && $amount >= $min && $amount <= $max;
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

        $events = [];
        if ($action->type === 'bet') {
            $amount = (int) $action->payload['amount'];
            $state['stacks'][$seat] -= $amount;
            $state['bets'][$seat] = $amount;
            $events[] = ['type' => 'bet', 'seat' => $seat, 'amount' => $amount];
            $next = $this->nextBettor($state, $seat);
            if ($next !== null) {
                $state['to_act'] = $next;

                return new Transition($state, $events);
            }

            return new Transition($this->deal($state, $random, $events), $events);
        }

        $index = $state['active'];
        $hand = $state['hands'][$seat][$index];
        $events[] = ['type' => $action->type, 'seat' => $seat, 'hand' => $index];
        switch ($action->type) {
            case 'hit':
                $hand['cards'][] = $this->draw($state, $random, $events);
                $hand = $this->settleDraw($hand, finished: false);
                break;
            case 'stand':
                $hand['status'] = 'stood';
                break;
            case 'double':
                $state['stacks'][$seat] -= $hand['bet'];
                $hand['bet'] *= 2;
                $hand['doubled'] = true;
                $hand['cards'][] = $this->draw($state, $random, $events);
                $hand = $this->settleDraw($hand, finished: true);
                break;
            case 'split':
                $state['stacks'][$seat] -= $hand['bet'];
                $aces = $hand['cards'][0][0] === 'A';
                $first = $this->newHand([$hand['cards'][0]], $hand['bet'], split: true);
                $second = $this->newHand([$hand['cards'][1]], $hand['bet'], split: true);
                $first['cards'][] = $this->draw($state, $random, $events);
                $second['cards'][] = $this->draw($state, $random, $events);
                // Split aces take one card each and stand.
                $first = $this->settleDraw($first, finished: $aces);
                $second = $this->settleDraw($second, finished: $aces);
                array_splice($state['hands'][$seat], $index, 1, [$first, $second]);
                $hand = $first;
                break;
        }
        $state['hands'][$seat][$index] = $hand;

        return new Transition($this->advance($state, $random, $events), $events);
    }

    /**
     * A timed-out player bets the minimum or stands.
     *
     * @param  array<string, mixed>  $state
     */
    public function timeoutAction(array $state, int $seat): Action
    {
        return $state['phase'] === 'betting'
            ? new Action('bet', ['amount' => $this->betRange($state, $seat)[0]])
            : new Action('stand');
    }

    /**
     * The player leaves: a bet placed before the deal goes back to their stack, hands in play are lost.
     *
     * @param  array<string, mixed>  $state
     */
    public function depart(array $state, int $seat, RandomSource $random): Transition
    {
        $events = [['type' => 'left', 'seat' => $seat]];
        $wasToAct = $state['to_act'] === $seat;
        $state['out'][$seat] = true;
        $state['stacks'][$seat] += $state['bets'][$seat];
        $state['bets'][$seat] = 0;
        foreach ($state['hands'][$seat] as $i => $hand) {
            if ($hand['result'] === null) {
                $state['hands'][$seat][$i]['status'] = 'bust';
                $state['hands'][$seat][$i]['result'] = 'lose';
            }
        }

        if ($state['result'] !== null) {
            return new Transition($state, $events);
        }
        if ($this->active($state) === []) {
            return new Transition($this->finishGame($state, 'table_empty'), $events);
        }
        if ($wasToAct) {
            if ($state['phase'] === 'betting') {
                $next = $this->nextBettor($state, $seat);
                $state = $next !== null ? [...$state, 'to_act' => $next] : $this->deal($state, $random, $events);
            } else {
                $state = $this->advance($state, $random, $events);
            }
        }

        return new Transition($state, $events);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function viewFor(array $state, ?int $seat): array
    {
        $view = $state;
        unset($view['shoe'], $view['cut'], $view['pause_ms']);
        $view['seat'] = $seat;
        $view['shoe_left'] = count($state['shoe']);
        $view['dealer'] = $state['hole_hidden'] ? [$state['dealer'][0], null] : $state['dealer'];
        $view['dealer_total'] = $state['dealer'] === [] ? null : BlackjackCards::total($state['hole_hidden'] ? [$state['dealer'][0]] : $state['dealer'])[0];
        foreach ($view['hands'] as $s => $hands) {
            foreach ($hands as $i => $hand) {
                [$total, $soft] = BlackjackCards::total($hand['cards']);
                $view['hands'][$s][$i]['total'] = $total;
                $view['hands'][$s][$i]['soft'] = $soft && $total < 21;
            }
        }

        return $view;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function outcome(array $state): ?Outcome
    {
        return $state['result'] === null ? null : new Outcome($state['result']['winners'], $state['result']['reason'], $state['result']['scores']);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function buyIn(array $options): int
    {
        return (int) ($options['starting_chips'] ?? 1000);
    }

    /**
     * The stack plus the chips at stake this round (bets before the deal, hands not yet settled).
     *
     * @param  array<string, mixed>  $state
     */
    public function chipsOf(array $state, int $seat): int
    {
        $atStake = $state['bets'][$seat];
        foreach ($state['hands'][$seat] as $hand) {
            $atStake += $hand['result'] === null ? $hand['bet'] : 0;
        }

        return $state['stacks'][$seat] + $atStake;
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
        $state = $this->startRound($state);

        return new Transition($state, [['type' => 'next_round', 'round' => $state['round']]]);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{0: int, 1: int}
     */
    private function betRange(array $state, int $seat): array
    {
        $stack = $state['stacks'][$seat];
        [$min, $max] = $state['limits'];

        return [min($min, $stack), min($max, $stack)];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $hand
     */
    private function canSplit(array $state, int $seat, array $hand): bool
    {
        return count($hand['cards']) === 2
            && BlackjackCards::value($hand['cards'][0]) === BlackjackCards::value($hand['cards'][1])
            && count($state['hands'][$seat]) < self::MAX_HANDS
            // Split aces can't be split again.
            && ! ($hand['split'] && $hand['cards'][0][0] === 'A');
    }

    /**
     * @param  list<string>  $cards
     * @return array<string, mixed>
     */
    private function newHand(array $cards, int $bet, bool $split = false): array
    {
        return ['cards' => $cards, 'bet' => $bet, 'doubled' => false, 'split' => $split, 'status' => 'playing', 'result' => null, 'payout' => 0];
    }

    /**
     * After a card was added: bust over 21, done at 21 or when no more cards may follow.
     *
     * @param  array<string, mixed>  $hand
     * @return array<string, mixed>
     */
    private function settleDraw(array $hand, bool $finished): array
    {
        $total = BlackjackCards::total($hand['cards'])[0];
        $hand['status'] = match (true) {
            $total > 21 => 'bust',
            $total === 21 || $finished => 'stood',
            default => 'playing',
        };

        return $hand;
    }

    /**
     * Seats still at the table.
     *
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    private function active(array $state): array
    {
        return array_keys(array_filter($state['out'], fn (bool $out) => ! $out));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function nextBettor(array $state, int $after): ?int
    {
        foreach ($this->active($state) as $seat) {
            if ($seat > $after && $state['bets'][$seat] === 0) {
                return $seat;
            }
        }

        return null;
    }

    /**
     * A new round: clear the table and ask the first seat for a bet.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function startRound(array $state): array
    {
        $n = count($state['stacks']);
        $state['round']++;
        $state['phase'] = 'betting';
        $state['bets'] = array_fill(0, $n, 0);
        $state['hands'] = array_fill(0, $n, []);
        $state['dealer'] = [];
        $state['hole_hidden'] = false;
        $state['active'] = 0;
        $state['to_act'] = $this->active($state)[0] ?? null;

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     */
    private function draw(array &$state, RandomSource $random, array &$events): string
    {
        if ($state['shoe'] === []) {
            $state['shoe'] = BlackjackCards::shoe($state['decks'], $random);
            $events[] = ['type' => 'shuffle'];
        }

        return array_shift($state['shoe']);
    }

    /**
     * Everyone has bet: deal, let the dealer peek, and give the first hand the turn.
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function deal(array $state, RandomSource $random, array &$events): array
    {
        if (count($state['shoe']) < $state['cut']) {
            $state['shoe'] = BlackjackCards::shoe($state['decks'], $random);
            $events[] = ['type' => 'shuffle'];
        }

        $players = array_values(array_filter($this->active($state), fn (int $seat) => $state['bets'][$seat] > 0));
        foreach ($players as $seat) {
            $state['hands'][$seat] = [$this->newHand([], $state['bets'][$seat])];
            $state['bets'][$seat] = 0;
        }
        for ($pass = 0; $pass < 2; $pass++) {
            foreach ($players as $seat) {
                $state['hands'][$seat][0]['cards'][] = $this->draw($state, $random, $events);
            }
            $state['dealer'][] = $this->draw($state, $random, $events);
        }
        $state['phase'] = 'playing';
        $state['hole_hidden'] = true;
        $events[] = ['type' => 'dealt', 'up' => $state['dealer'][0]];

        foreach ($players as $seat) {
            if (BlackjackCards::isBlackjack($state['hands'][$seat][0]['cards'])) {
                $state['hands'][$seat][0]['status'] = 'blackjack';
            }
        }

        // The dealer peeks under an ace or a ten: a blackjack ends the round before anyone plays.
        if (BlackjackCards::value($state['dealer'][0]) >= 10 && BlackjackCards::isBlackjack($state['dealer'])) {
            return $this->finishRound($state, $events);
        }

        $state['to_act'] = null;
        $state['active'] = 0;

        return $this->advance($state, $random, $events, fromStart: true);
    }

    /**
     * Give the turn to the next hand still playing, or play the dealer's hand.
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function advance(array $state, RandomSource $random, array &$events, bool $fromStart = false): array
    {
        $current = $fromStart ? -1 : ($state['to_act'] ?? -1);
        foreach ($this->active($state) as $seat) {
            if ($seat < $current) {
                continue;
            }
            foreach ($state['hands'][$seat] as $i => $hand) {
                if ($hand['status'] === 'playing') {
                    $state['to_act'] = $seat;
                    $state['active'] = $i;

                    return $state;
                }
            }
        }

        // The dealer reveals and draws, unless every hand is already decided (busts and blackjacks).
        $state['to_act'] = null;
        $state['hole_hidden'] = false;
        $standing = false;
        foreach ($state['hands'] as $hands) {
            foreach ($hands as $hand) {
                $standing = $standing || $hand['status'] === 'stood';
            }
        }
        while ($standing) {
            [$total, $soft] = BlackjackCards::total($state['dealer']);
            if ($total > 17 || ($total === 17 && ! ($soft && $state['h17']))) {
                break;
            }
            $state['dealer'][] = $this->draw($state, $random, $events);
        }

        return $this->finishRound($state, $events);
    }

    /**
     * Pay every hand, then pause before the next round (or end the game).
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function finishRound(array $state, array &$events): array
    {
        $state['hole_hidden'] = false;
        $state['to_act'] = null;
        [$dealer] = BlackjackCards::total($state['dealer']);
        $dealerBlackjack = BlackjackCards::isBlackjack($state['dealer']);
        $net = array_fill(0, count($state['stacks']), 0);

        foreach ($state['hands'] as $seat => $hands) {
            foreach ($hands as $i => $hand) {
                if ($hand['result'] !== null) {
                    $net[$seat] -= $hand['bet'];    // forfeited by leaving

                    continue;
                }
                $natural = $hand['status'] === 'blackjack';
                [$total] = BlackjackCards::total($hand['cards']);
                $result = match (true) {
                    $natural && $dealerBlackjack => 'push',
                    $natural => 'blackjack',
                    $hand['status'] === 'bust' || $dealerBlackjack => 'lose',
                    $dealer > 21 || $total > $dealer => 'win',
                    $total === $dealer => 'push',
                    default => 'lose',
                };
                $payout = match ($result) {
                    'blackjack' => $hand['bet'] + intdiv($hand['bet'] * 3, 2),
                    'win' => $hand['bet'] * 2,
                    'push' => $hand['bet'],
                    default => 0,
                };
                $state['hands'][$seat][$i]['result'] = $result;
                $state['hands'][$seat][$i]['payout'] = $payout;
                $state['stacks'][$seat] += $payout;
                $net[$seat] += $payout - $hand['bet'];
            }
        }

        $state['last_round'] = ['number' => $state['round'], 'dealer' => $state['dealer'], 'dealer_total' => $dealer, 'net' => $net];
        $events[] = ['type' => 'round_over', 'round' => $state['round'], 'dealer_total' => $dealer, 'net' => $net];
        $state['phase'] = 'over';

        // Seats without chips leave the table.
        foreach ($state['stacks'] as $seat => $stack) {
            if ($stack === 0) {
                $state['out'][$seat] = true;
            }
        }

        return match (true) {
            $this->active($state) === [] => $this->finishGame($state, 'last_standing'),
            $state['round'] >= $state['rounds'] => $this->finishGame($state, 'rounds_played'),
            default => $state,
        };
    }

    /**
     * Seats ahead of their buy-in win; when nobody is ahead and someone is behind, the house wins.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function finishGame(array $state, string $reason): array
    {
        $scores = [];
        foreach ($state['stacks'] as $seat => $_) {
            $scores[$seat] = $this->chipsOf($state, $seat) - $state['start'][$seat];
        }
        $winners = array_keys(array_filter($scores, fn (int $net) => $net > 0));
        if ($winners === [] && min($scores) < 0) {
            $winners = [Outcome::HOUSE];
        }
        $state['to_act'] = null;
        $state['result'] = ['winners' => $winners, 'reason' => $reason, 'scores' => $scores];

        return $state;
    }
}
