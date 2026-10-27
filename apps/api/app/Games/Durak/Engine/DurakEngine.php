<?php

namespace App\Games\Durak\Engine;

use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\RandomSource;

/**
 * Podkidnoy durak for two players with a 36-card deck.
 *
 * Play is strictly turn by turn: the attacker leads a card; the defender beats it ("defend" with a
 * card) or picks up ("take"); while every card is beaten, the attacker may throw in another card of
 * a rank on the table ("attack") or end the bout ("done"). After "take" the attacker may still throw
 * in before the defender picks everything up. A bout holds at most six attacks and never more than
 * the defender held when it began. When the attacker has nothing left to throw, the bout ends by
 * itself.
 *
 * After a bout both players draw up to six (attacker first); the defender attacks next unless they
 * picked up. Once the deck is empty, a player without cards is out: the other is the durak. Both out
 * at once is a draw.
 *
 * State: deck (hidden; the last card is the face-up trump card), trump (suit), hands, table
 * (list of {attack, defense}), attacker, defender, taking, limit, discarded, result.
 */
final class DurakEngine implements GameEngine
{
    public const HAND = 6;

    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        $deck = DurakCards::shuffled($random);
        $hands = [array_splice($deck, 0, self::HAND), array_splice($deck, 0, self::HAND)];
        $trumpCard = $deck[count($deck) - 1];
        $trump = DurakCards::suit($trumpCard);

        // The lowest trump leads; without trumps in either hand, the dealer's left (seat 0) does.
        $lowest = [null, null];
        foreach ($hands as $seat => $hand) {
            foreach ($hand as $card) {
                if (DurakCards::suit($card) === $trump && ($lowest[$seat] === null || DurakCards::rank($card) < $lowest[$seat])) {
                    $lowest[$seat] = DurakCards::rank($card);
                }
            }
        }
        $attacker = match (true) {
            $lowest[0] === null && $lowest[1] === null => 0,
            $lowest[0] === null => 1,
            $lowest[1] === null => 0,
            default => $lowest[0] < $lowest[1] ? 0 : 1,
        };

        return [
            'deck' => $deck,
            'trump' => $trump,
            'trump_card' => $trumpCard,
            'hands' => $hands,
            'table' => [],
            'attacker' => $attacker,
            'defender' => 1 - $attacker,
            'taking' => false,
            'limit' => min(6, count($hands[1 - $attacker])),
            'discarded' => 0,
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

        return [$this->unbeaten($state) && ! $state['taking'] ? $state['defender'] : $state['attacker']];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function legalActions(array $state, int $seat): array
    {
        if ($this->seatsToAct($state) !== [$seat]) {
            return [];
        }
        $hand = $state['hands'][$seat];

        if ($seat === $state['defender']) {
            $attack = $state['table'][count($state['table']) - 1]['attack'];
            $actions = [];
            foreach ($hand as $card) {
                if (DurakCards::beats($card, $attack, $state['trump'])) {
                    $actions[] = new Action('defend', ['card' => $card]);
                }
            }
            $actions[] = new Action('take');

            return $actions;
        }

        $actions = array_map(fn (string $card) => new Action('attack', ['card' => $card]), $this->playable($state));
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

        $events = [];
        switch ($action->type) {
            case 'attack':
                $card = (string) $action->payload['card'];
                $state['hands'][$seat] = $this->without($state['hands'][$seat], $card);
                $state['table'][] = ['attack' => $card, 'defense' => null];
                $events[] = ['type' => 'attacked', 'seat' => $seat, 'card' => $card];
                break;
            case 'defend':
                $card = (string) $action->payload['card'];
                $state['hands'][$seat] = $this->without($state['hands'][$seat], $card);
                $state['table'][count($state['table']) - 1]['defense'] = $card;
                $events[] = ['type' => 'defended', 'seat' => $seat, 'card' => $card];
                break;
            case 'take':
                $state['taking'] = true;
                $events[] = ['type' => 'taking', 'seat' => $seat];
                break;
            case 'done':
                return new Transition(...$this->endBout($state, $events));
        }

        // Nothing left to throw in: the bout ends on its own.
        if (($state['taking'] || ! $this->unbeaten($state)) && $this->playable($state) === []) {
            return new Transition(...$this->endBout($state, $events));
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
            'limit' => $state['limit'],
            'discarded' => $state['discarded'],
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
     * Cards the attacker may play now: anything to open a bout; otherwise ranks already on the table,
     * within the bout's limit and the defender's hand.
     *
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    private function playable(array $state): array
    {
        $hand = $state['hands'][$state['attacker']];
        if ($state['table'] === []) {
            return $hand;
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

        foreach ([$state['attacker'], $state['defender']] as $seat) {
            $need = max(0, self::HAND - count($state['hands'][$seat]));
            $drawn = array_splice($state['deck'], 0, $need);
            array_push($state['hands'][$seat], ...$drawn);
            if ($drawn !== []) {
                $events[] = ['type' => 'drew', 'seat' => $seat, 'count' => count($drawn)];
            }
        }

        if (! $took) {
            [$state['attacker'], $state['defender']] = [$state['defender'], $state['attacker']];
        }
        $state['limit'] = min(6, count($state['hands'][$state['defender']]));

        if ($state['deck'] === []) {
            $out = array_keys(array_filter($state['hands'], fn (array $hand) => $hand === []));
            $state['result'] = match (count($out)) {
                0 => null,
                1 => ['winners' => $out, 'reason' => 'durak'],
                default => ['winners' => [], 'reason' => 'draw'],
            };
        }

        return [$state, $events];
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
