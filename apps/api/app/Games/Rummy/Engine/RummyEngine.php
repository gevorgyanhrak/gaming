<?php

namespace App\Games\Rummy\Engine;

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
 * Classic rummy for 2–6 players, to `target` points (100). One 52-card deck (two for five or six players);
 * ten cards each with two players, seven with three or four, six with five or six.
 *
 * A turn: draw {from: stock|discard}, then any number of meld {cards: "AS,2S,3S"} (a set of three or four
 * of a rank in different suits, or a run of three or more in a suit; aces are low) and layoff {card, meld}
 * (a card that extends a meld on the table), then discard {card} — not the card just taken from the
 * discard pile, unless it is the last one. A player who runs out of cards goes out and scores the cards
 * left in everyone else's hand (ace 1, pictures 10, others their number). When the stock runs out, the
 * discard pile under its top card is shuffled into a new stock, once a round; when that runs out too, the
 * round ends and nobody scores. After each round the table pauses.
 *
 * State: target, scores, out, dealer, round, phase (draw|play|over), stock (hidden), discard, hands
 * (hidden), melds [{kind: set|run, cards, seat}], to_act, taken (the card just taken from the discard
 * pile), restocked (this round), last_round (winner or null, points, hands shown), pause_ms, result.
 */
final class RummyEngine implements GameEngine, HandlesDepartures, HandlesTimeouts, PausesBetweenRounds
{
    public const RANKS = 'A23456789TJQK';

    public const SUITS = ['S', 'H', 'D', 'C'];

    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        $n = $setup->seats;
        $state = [
            'target' => (int) ($setup->options['target'] ?? 100),
            'scores' => array_fill(0, $n, 0),
            'out' => array_fill(0, $n, false),
            'dealer' => $random->int(0, $n - 1),
            'round' => 0,
            'last_round' => null,
            'pause_ms' => (int) ($setup->options['round_pause_ms'] ?? 5000),
            'result' => null,
        ];

        return $this->deal($state, $random);
    }

    public static function value(string $card): int
    {
        return match ($card[0]) {
            'A' => 1,
            'T', 'J', 'Q', 'K' => 10,
            default => (int) $card[0],
        };
    }

    public static function rank(string $card): int
    {
        return (int) strpos(self::RANKS, $card[0]);
    }

    /**
     * Whether $cards form a set or a run; returns the kind, or null.
     *
     * @param  list<string>  $cards
     */
    public static function meldKind(array $cards): ?string
    {
        if (count($cards) < 3) {
            return null;
        }
        $ranks = array_map(self::rank(...), $cards);
        $suits = array_map(fn (string $c) => $c[1], $cards);
        if (count(array_unique($ranks)) === 1 && count($cards) <= 4 && count(array_unique($suits)) === count($cards)) {
            return 'set';
        }
        if (count(array_unique($suits)) === 1) {
            sort($ranks);
            foreach ($ranks as $i => $rank) {
                if ($i > 0 && $rank !== $ranks[$i - 1] + 1) {
                    return null;
                }
            }

            return 'run';
        }

        return null;
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
        if ($state['phase'] === 'draw') {
            $actions = [];
            // An empty stock is remade from the discard pile under its top card.
            if ($state['stock'] !== [] || (! $state['restocked'] && count($state['discard']) > 1)) {
                $actions[] = new Action('draw', ['from' => 'stock']);
            }
            if ($state['discard'] !== []) {
                $actions[] = new Action('draw', ['from' => 'discard']);
            }

            return $actions;
        }

        $hand = $state['hands'][$seat];
        $actions = [];
        foreach ($this->possibleMelds($hand) as $meld) {
            $actions[] = new Action('meld', ['cards' => implode(',', $meld)]);
        }
        foreach (array_unique($hand) as $card) {
            foreach ($state['melds'] as $i => $meld) {
                if (self::meldKind([...$meld['cards'], $card]) !== null) {
                    $actions[] = new Action('layoff', ['card' => $card, 'meld' => $i]);
                }
            }
        }
        foreach (array_values(array_unique($hand)) as $card) {
            if ($card !== $state['taken'] || count($hand) === 1 || count(array_keys($hand, $card, true)) > 1) {
                $actions[] = new Action('discard', ['card' => $card]);
            }
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition
    {
        if (! collect($this->legalActions($state, $seat))->contains(fn (Action $a) => $a->equals($action))) {
            throw new IllegalAction('That move is not allowed.');
        }
        $events = [['type' => $action->type, 'seat' => $seat]];

        switch ($action->type) {
            case 'draw':
                if ($action->payload['from'] === 'discard') {
                    $card = array_pop($state['discard']);
                    $state['taken'] = $card;
                    $events[0]['card'] = $card;
                } else {
                    if ($state['stock'] === []) {
                        $state = $this->restock($state, $random);
                        $events[] = ['type' => 'restock'];
                    }
                    $card = array_shift($state['stock']);
                    $state['taken'] = null;
                }
                $state['hands'][$seat][] = $card;
                $state['phase'] = 'play';
                break;
            case 'meld':
                $cards = explode(',', (string) $action->payload['cards']);
                $state['hands'][$seat] = $this->without($state['hands'][$seat], $cards);
                $state['melds'][] = ['kind' => self::meldKind($cards), 'cards' => $this->ordered($cards), 'seat' => $seat];
                $events[0]['cards'] = $cards;
                break;
            case 'layoff':
                $card = (string) $action->payload['card'];
                $i = (int) $action->payload['meld'];
                $state['hands'][$seat] = $this->without($state['hands'][$seat], [$card]);
                $state['melds'][$i]['cards'] = $this->ordered([...$state['melds'][$i]['cards'], $card]);
                $events[0] += ['card' => $card, 'meld' => $i];
                break;
            case 'discard':
                $card = (string) $action->payload['card'];
                $state['hands'][$seat] = $this->without($state['hands'][$seat], [$card]);
                $state['discard'][] = $card;
                $state['taken'] = null;
                $events[0]['card'] = $card;
                if ($state['hands'][$seat] !== []) {
                    // The stock is spent and can't be remade: the round ends without a winner.
                    if ($state['stock'] === [] && ($state['restocked'] || count($state['discard']) <= 1)) {
                        $events[] = ['type' => 'stock_out'];

                        return new Transition($this->finishRound($state, null, $events), $events);
                    }
                    $state['to_act'] = $this->next($state, $seat);
                    $state['phase'] = 'draw';
                }
                break;
        }

        if ($state['hands'][$seat] === []) {
            return new Transition($this->finishRound($state, $seat, $events), $events);
        }

        return new Transition($state, $events);
    }

    /**
     * A timed-out player draws from the stock, then discards their highest card.
     *
     * @param  array<string, mixed>  $state
     */
    public function timeoutAction(array $state, int $seat): ?Action
    {
        if ($state['phase'] === 'draw') {
            return $this->legalActions($state, $seat)[0] ?? null;
        }
        $discards = array_values(array_filter($this->legalActions($state, $seat), fn (Action $a) => $a->type === 'discard'));
        usort($discards, fn (Action $a, Action $b) => self::value((string) $b->payload['card']) <=> self::value((string) $a->payload['card']));

        return $discards[0] ?? null;
    }

    /**
     * The player leaves: their cards leave the game; with one player left, they win.
     *
     * @param  array<string, mixed>  $state
     */
    public function depart(array $state, int $seat, RandomSource $random): Transition
    {
        $state['out'][$seat] = true;
        $state['hands'][$seat] = [];
        $active = $this->active($state);
        if (count($active) <= 1) {
            $state['to_act'] = null;
            $state['result'] = ['winners' => $active, 'reason' => 'last_standing'];
        } elseif ($state['to_act'] === $seat && $state['phase'] !== 'over') {
            $state['to_act'] = $this->next($state, $seat);
            $state['phase'] = 'draw';
            $state['taken'] = null;
        }

        return new Transition($state, [['type' => 'left', 'seat' => $seat]]);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function viewFor(array $state, ?int $seat): array
    {
        $view = $state;
        unset($view['stock'], $view['hands'], $view['pause_ms']);
        $view['seat'] = $seat;
        $view['hand'] = $seat === null ? [] : $state['hands'][$seat];
        $view['hand_sizes'] = array_map('count', $state['hands']);
        $view['stock_size'] = count($state['stock']);
        $view['discard_top'] = $state['discard'] === [] ? null : $state['discard'][count($state['discard']) - 1];
        $view['discard_size'] = count($state['discard']);
        unset($view['discard']);

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
     * Every set and run in a hand (each set of four also as its sets of three; each run with its shorter runs).
     *
     * @param  list<string>  $hand
     * @return list<list<string>>
     */
    public function possibleMelds(array $hand): array
    {
        $unique = array_values(array_unique($hand));
        $melds = [];
        // Sets: per rank, the cards of different suits.
        $byRank = [];
        foreach ($unique as $card) {
            $byRank[$card[0]][$card[1]] = $card;
        }
        foreach ($byRank as $cards) {
            $cards = array_values($cards);
            if (count($cards) >= 3) {
                if (count($cards) === 4) {
                    $melds[] = $cards;
                }
                for ($skip = 0; $skip < count($cards); $skip++) {
                    if (count($cards) === 4 || $skip === 0) {
                        $three = count($cards) === 4 ? array_values(array_diff($cards, [$cards[$skip]])) : $cards;
                        $melds[] = $three;
                    }
                }
            }
        }
        // Runs: per suit, every stretch of three or more consecutive ranks.
        foreach (self::SUITS as $suit) {
            $ranks = [];
            foreach ($unique as $card) {
                if ($card[1] === $suit) {
                    $ranks[self::rank($card)] = $card;
                }
            }
            ksort($ranks);
            for ($start = 0; $start < 13; $start++) {
                $run = [];
                for ($r = $start; $r < 13 && isset($ranks[$r]); $r++) {
                    $run[] = $ranks[$r];
                    if (count($run) >= 3) {
                        $melds[] = $run;
                    }
                }
            }
        }

        $seen = [];
        $result = [];
        foreach ($melds as $meld) {
            $meld = $this->ordered($meld);
            $key = implode(',', $meld);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $meld;
            }
        }

        return $result;
    }

    /**
     * Cards in a canonical order: by rank, then suit.
     *
     * @param  list<string>  $cards
     * @return list<string>
     */
    private function ordered(array $cards): array
    {
        usort($cards, fn (string $a, string $b) => [self::rank($a), array_search($a[1], self::SUITS, true)] <=> [self::rank($b), array_search($b[1], self::SUITS, true)]);

        return $cards;
    }

    /**
     * $hand without one copy of each of $cards.
     *
     * @param  list<string>  $hand
     * @param  list<string>  $cards
     * @return list<string>
     */
    private function without(array $hand, array $cards): array
    {
        foreach ($cards as $card) {
            $i = array_search($card, $hand, true);
            if ($i !== false) {
                unset($hand[$i]);
            }
        }

        return array_values($hand);
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
     * @param  array<string, mixed>  $state
     */
    private function next(array $state, int $seat): int
    {
        $n = count($state['scores']);
        $next = $seat;
        do {
            $next = ($next + 1) % $n;
        } while ($state['out'][$next] && $next !== $seat);

        return $next;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function restock(array $state, RandomSource $random): array
    {
        $top = array_pop($state['discard']);
        $cards = $state['discard'];
        for ($i = count($cards) - 1; $i > 0; $i--) {
            $j = $random->int(0, $i);
            [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
        }
        $state['stock'] = $cards;
        $state['discard'] = $top === null ? [] : [$top];
        $state['restocked'] = true;

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function finishRound(array $state, ?int $winner, array &$events): array
    {
        $left = [];
        foreach ($this->active($state) as $seat) {
            $left[$seat] = array_sum(array_map(self::value(...), $state['hands'][$seat]));
        }
        $points = $winner === null ? 0 : array_sum($left);
        if ($winner !== null) {
            $state['scores'][$winner] += $points;
        }
        $state['last_round'] = ['number' => $state['round'], 'winner' => $winner, 'points' => $points, 'left' => $left, 'hands' => $state['hands']];
        $events[] = ['type' => 'round_over', 'winner' => $winner, 'points' => $points];
        $state['phase'] = 'over';
        $state['to_act'] = null;

        $best = max($state['scores']);
        $top = array_keys($state['scores'], $best, true);
        if ($best >= $state['target'] && count($top) === 1) {
            $state['result'] = ['winners' => $top, 'reason' => 'target_reached'];
        }

        return $state;
    }

    /**
     * Shuffle, deal, turn up the first discard; the player after the dealer starts.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function deal(array $state, RandomSource $random): array
    {
        $n = count($state['scores']);
        $decks = $n >= 5 ? 2 : 1;
        $cards = [];
        for ($d = 0; $d < $decks; $d++) {
            foreach (self::SUITS as $suit) {
                foreach (str_split(self::RANKS) as $rank) {
                    $cards[] = $rank.$suit;
                }
            }
        }
        for ($i = count($cards) - 1; $i > 0; $i--) {
            $j = $random->int(0, $i);
            [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
        }
        $size = match (true) {
            $n === 2 => 10,
            $n <= 4 => 7,
            default => 6,
        };

        $state['round']++;
        if ($state['round'] > 1) {
            $state['dealer'] = $this->next($state, $state['dealer']);
        }
        $state['hands'] = array_fill(0, $n, []);
        foreach ($this->active($state) as $seat) {
            $state['hands'][$seat] = array_splice($cards, 0, $size);
        }
        $state['discard'] = [array_shift($cards)];
        $state['stock'] = $cards;
        $state['melds'] = [];
        $state['phase'] = 'draw';
        $state['taken'] = null;
        $state['restocked'] = false;
        $state['to_act'] = $this->next($state, $state['dealer']);

        return $state;
    }
}
