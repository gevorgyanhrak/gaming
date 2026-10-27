<?php

namespace App\Games\Durak\Engine;

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
 * Podkidnoy durak for 2–6 players with a 36-card deck.
 *
 * Play is strictly turn by turn: the attacker leads a card; the defender (the next player round the
 * table) beats it ("defend" with a card) or picks up ("take"). While every card is beaten — or after
 * "take" — the other players may throw in cards of a rank on the table ("attack"), one at a time: the
 * main attacker first, then the others round the table from the defender. A player who has nothing
 * to add passes ("done"); a new card on the table gives everyone another chance. The bout ends when
 * every thrower has passed or has nothing that fits. A bout holds at most six attacks and never more
 * than the defender held when it began.
 *
 * After a bout everyone draws up to six (attacker first, defender last). The defender attacks next,
 * or, if they picked up, the player after them. Once the deck is empty, players without cards are
 * out; the last one holding cards is the durak. Nobody left holding cards is a draw.
 *
 * State: deck (hidden; the last card is the face-up trump card), trump (suit), trump_card, hands,
 * table (list of {attack, defense}), attacker, defender, taking, passed (throwers who passed since the
 * last card was added), limit, discarded, left (players who left the table), result.
 */
final class DurakEngine implements GameEngine, HandlesDepartures, HandlesTimeouts
{
    public const HAND = 6;

    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        $seats = max(2, $setup->seats);
        $deck = DurakCards::shuffled($random);
        $hands = [];
        for ($seat = 0; $seat < $seats; $seat++) {
            $hands[] = array_splice($deck, 0, self::HAND);
        }
        // With six players every card is dealt; the last one dealt shows the trump.
        $trumpCard = $deck === [] ? $hands[$seats - 1][self::HAND - 1] : $deck[count($deck) - 1];
        $trump = DurakCards::suit($trumpCard);

        // The lowest trump leads; without trumps in any hand, seat 0 does.
        $attacker = 0;
        $lowest = null;
        foreach ($hands as $seat => $hand) {
            foreach ($hand as $card) {
                if (DurakCards::suit($card) === $trump && ($lowest === null || DurakCards::rank($card) < $lowest)) {
                    [$lowest, $attacker] = [DurakCards::rank($card), $seat];
                }
            }
        }
        $defender = ($attacker + 1) % $seats;

        return [
            'deck' => $deck,
            'trump' => $trump,
            'trump_card' => $trumpCard,
            'hands' => $hands,
            'table' => [],
            'attacker' => $attacker,
            'defender' => $defender,
            'taking' => false,
            'passed' => [],
            'limit' => min(6, count($hands[$defender])),
            'discarded' => 0,
            'left' => array_fill(0, $seats, false),
            'result' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function seatsToAct(array $state): array
    {
        if ($state['result'] !== null) {
            return [];
        }
        if ($this->unbeaten($state) && ! $state['taking']) {
            return [$state['defender']];
        }
        if ($state['table'] === []) {
            return [$state['attacker']];
        }
        $thrower = $this->currentThrower($state);

        return $thrower === null ? [] : [$thrower];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function legalActions(array $state, int $seat): array
    {
        if ($this->seatsToAct($state) !== [$seat]) {
            return [];
        }

        if ($seat === $state['defender'] && $this->unbeaten($state) && ! $state['taking']) {
            $attack = $state['table'][count($state['table']) - 1]['attack'];
            $actions = [];
            foreach ($state['hands'][$seat] as $card) {
                if (DurakCards::beats($card, $attack, $state['trump'])) {
                    $actions[] = new Action('defend', ['card' => $card]);
                }
            }
            $actions[] = new Action('take');

            return $actions;
        }

        $actions = array_map(fn (string $card) => new Action('attack', ['card' => $card]), $this->playable($state, $seat));
        if ($state['table'] !== []) {
            $actions[] = new Action('done');
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition
    {
        if (! collect($this->legalActions($state, $seat))->contains(fn (Action $legal) => $legal->equals($action))) {
            throw new IllegalAction('That move is not allowed.');
        }
        $state['passed'] ??= [];

        $events = [];
        switch ($action->type) {
            case 'attack':
                $card = (string) $action->payload['card'];
                $state['hands'][$seat] = $this->without($state['hands'][$seat], $card);
                $state['table'][] = ['attack' => $card, 'defense' => null];
                $state['passed'] = [];
                $events[] = ['type' => 'attacked', 'seat' => $seat, 'card' => $card];
                break;
            case 'defend':
                $card = (string) $action->payload['card'];
                $state['hands'][$seat] = $this->without($state['hands'][$seat], $card);
                $state['table'][count($state['table']) - 1]['defense'] = $card;
                // A new rank on the table: everyone may throw in again.
                $state['passed'] = [];
                $events[] = ['type' => 'defended', 'seat' => $seat, 'card' => $card];
                break;
            case 'take':
                $state['taking'] = true;
                $state['passed'] = [];
                $events[] = ['type' => 'taking', 'seat' => $seat];
                break;
            case 'done':
                $state['passed'][] = $seat;
                break;
        }

        // Nobody has anything (more) to throw in: the bout ends on its own.
        if (($state['taking'] || ! $this->unbeaten($state)) && $this->currentThrower($state) === null) {
            return new Transition(...$this->endBout($state, $events));
        }

        return new Transition($state, $events);
    }

    /**
     * At tables of three or more a player who runs out of time passes, picks up, or leads their lowest
     * card, so the others can play on. Heads-up, running out of time loses.
     *
     * @param  array<string, mixed>  $state
     */
    public function timeoutAction(array $state, int $seat): ?Action
    {
        if (count($state['hands']) <= 2) {
            return null;
        }
        $legal = $this->legalActions($state, $seat);
        $types = array_map(fn (Action $a) => $a->type, $legal);

        if (in_array('take', $types, true)) {
            return new Action('take');
        }
        if (in_array('done', $types, true)) {
            return new Action('done');
        }
        usort($legal, fn (Action $a, Action $b) => DurakCards::strength((string) $a->payload['card'], $state['trump']) <=> DurakCards::strength((string) $b->payload['card'], $state['trump']));

        return $legal[0] ?? null;
    }

    /**
     * The player leaves the table: their cards are put aside, and a bout they were attacking or
     * defending is cleared, the next player attacking.
     *
     * @param  array<string, mixed>  $state
     */
    public function depart(array $state, int $seat, RandomSource $random): Transition
    {
        $state['left'] ??= array_fill(0, count($state['hands']), false);
        $state['passed'] ??= [];
        $events = [['type' => 'left', 'seat' => $seat]];

        $state['left'][$seat] = true;
        $state['discarded'] += count($state['hands'][$seat]);
        $state['hands'][$seat] = [];
        $state['passed'] = array_values(array_diff($state['passed'], [$seat]));

        if ($state['result'] === null && ($seat === $state['attacker'] || $seat === $state['defender'])) {
            $state['discarded'] += $this->tableCount($state);
            $state['table'] = [];
            $state['taking'] = false;
            $state['passed'] = [];
            $state['attacker'] = $this->nextActive($state, $seat);
            $state['defender'] = $this->nextActive($state, $state['attacker']);
            $this->refill($state, $events);
            $state['limit'] = min(6, count($state['hands'][$state['defender']]));
        }

        $state['result'] ??= $this->result($state);

        // A bout can't continue if nobody can act in it any more.
        if ($state['result'] === null && $state['table'] !== [] && ($state['taking'] || ! $this->unbeaten($state)) && $this->currentThrower($state) === null) {
            [$state, $events] = $this->endBout($state, $events);
        }

        return new Transition($state, $events);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function viewFor(array $state, ?int $seat): array
    {
        return [
            'hand' => $seat === null ? [] : $state['hands'][$seat],
            'hand_counts' => array_map('count', $state['hands']),
            'deck_count' => count($state['deck']),
            'trump' => $state['trump'],
            // The face-up trump card is the last one drawn.
            'trump_card' => $state['deck'] === [] ? null : $state['trump_card'],
            'table' => $state['table'],
            'attacker' => $state['attacker'],
            'defender' => $state['defender'],
            'taking' => $state['taking'],
            'passed' => $state['passed'] ?? [],
            'limit' => $state['limit'],
            'discarded' => $state['discarded'],
            'left' => $state['left'] ?? array_fill(0, count($state['hands']), false),
            'result' => $state['result'],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function outcome(array $state): ?Outcome
    {
        return $state['result'] === null ? null : new Outcome($state['result']['winners'], $state['result']['reason']);
    }

    /**
     * Cards $seat may play now: anything to open a bout (attacker); otherwise ranks already on the
     * table, within the bout's limit and the defender's hand.
     *
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    private function playable(array $state, int $seat): array
    {
        $hand = $state['hands'][$seat];
        if ($state['table'] === []) {
            return $seat === $state['attacker'] ? $hand : [];
        }

        $unbeaten = count(array_filter($state['table'], fn (array $pair) => $pair['defense'] === null));
        if (count($state['table']) >= $state['limit'] || $unbeaten >= count($state['hands'][$state['defender']])) {
            return [];
        }

        $ranks = [];
        foreach ($state['table'] as $pair) {
            $ranks[$pair['attack'][0]] = true;
            if ($pair['defense'] !== null) {
                $ranks[$pair['defense'][0]] = true;
            }
        }

        return array_values(array_filter($hand, fn (string $card) => isset($ranks[$card[0]])));
    }

    /**
     * Who may throw in, in order: the main attacker, then the others round the table from the
     * defender (never the defender).
     *
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    private function throwers(array $state): array
    {
        $n = count($state['hands']);
        $order = [$state['attacker']];
        for ($i = 1; $i < $n; $i++) {
            $seat = ($state['defender'] + $i) % $n;
            if ($seat !== $state['attacker'] && $seat !== $state['defender'] && ! ($state['left'][$seat] ?? false)) {
                $order[] = $seat;
            }
        }

        return $order;
    }

    /**
     * The first thrower who hasn't passed and has something to throw in, if any.
     *
     * @param  array<string, mixed>  $state
     */
    private function currentThrower(array $state): ?int
    {
        foreach ($this->throwers($state) as $seat) {
            if (! in_array($seat, $state['passed'] ?? [], true) && $this->playable($state, $seat) !== []) {
                return $seat;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function unbeaten(array $state): bool
    {
        return $state['table'] !== [] && $state['table'][count($state['table']) - 1]['defense'] === null;
    }

    /**
     * Discard or pick up the table, draw up to six, pass the attack and check for the end.
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function endBout(array $state, array $events): array
    {
        $cards = [];
        foreach ($state['table'] as $pair) {
            $cards[] = $pair['attack'];
            if ($pair['defense'] !== null) {
                $cards[] = $pair['defense'];
            }
        }

        $took = $state['taking'];
        if ($took) {
            array_push($state['hands'][$state['defender']], ...$cards);
            $events[] = ['type' => 'took', 'seat' => $state['defender'], 'count' => count($cards)];
        } else {
            $state['discarded'] += count($cards);
            $events[] = ['type' => 'beaten', 'count' => count($cards)];
        }
        $state['table'] = [];
        $state['taking'] = false;
        $state['passed'] = [];

        $this->refill($state, $events);

        $defender = $state['defender'];
        $state['attacker'] = ! $took && $this->isActive($state, $defender) ? $defender : $this->nextActive($state, $defender);
        $state['defender'] = $this->nextActive($state, $state['attacker']);
        $state['limit'] = min(6, count($state['hands'][$state['defender']]));
        $state['result'] = $this->result($state);

        return [$state, $events];
    }

    /**
     * Everyone draws up to six while the deck lasts: the attacker first, the defender last.
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     */
    private function refill(array &$state, array &$events): void
    {
        foreach ([...$this->throwers($state), $state['defender']] as $seat) {
            if ($state['left'][$seat] ?? false) {
                continue;
            }
            $drawn = array_splice($state['deck'], 0, max(0, self::HAND - count($state['hands'][$seat])));
            array_push($state['hands'][$seat], ...$drawn);
            if ($drawn !== []) {
                $events[] = ['type' => 'drew', 'seat' => $seat, 'count' => count($drawn)];
            }
        }
    }

    /**
     * Still in the game: hasn't left, and holds cards or can still draw.
     *
     * @param  array<string, mixed>  $state
     */
    private function isActive(array $state, int $seat): bool
    {
        return ! ($state['left'][$seat] ?? false) && ($state['hands'][$seat] !== [] || $state['deck'] !== []);
    }

    /**
     * The next player still in the game after $seat (or $seat itself when nobody else is).
     *
     * @param  array<string, mixed>  $state
     */
    private function nextActive(array $state, int $seat): int
    {
        $n = count($state['hands']);
        for ($i = 1; $i <= $n; $i++) {
            if ($this->isActive($state, ($seat + $i) % $n)) {
                return ($seat + $i) % $n;
            }
        }

        return $seat;
    }

    /**
     * The end of the game, once at most one player is still in it.
     *
     * @param  array<string, mixed>  $state
     * @return array{winners: list<int>, reason: string}|null
     */
    private function result(array $state): ?array
    {
        $n = count($state['hands']);
        $active = array_values(array_filter(range(0, $n - 1), fn (int $s) => $this->isActive($state, $s)));
        if (count($active) > 1) {
            return null;
        }
        // Players who got rid of their cards (not those who left the table).
        $finished = array_values(array_filter(range(0, $n - 1), fn (int $s) => ! ($state['left'][$s] ?? false) && ! in_array($s, $active, true)));

        if ($active === []) {
            return ['winners' => [], 'reason' => 'draw'];
        }
        if ($finished === []) {
            // Everyone else left the table.
            return ['winners' => $active, 'reason' => 'last_player'];
        }

        return ['winners' => $finished, 'reason' => 'durak'];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function tableCount(array $state): int
    {
        return array_sum(array_map(fn (array $pair) => $pair['defense'] === null ? 1 : 2, $state['table']));
    }

    /**
     * @param  list<string>  $hand
     * @return list<string>
     */
    private function without(array $hand, string $card): array
    {
        return array_values(array_diff($hand, [$card]));
    }
}
