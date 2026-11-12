<?php

namespace App\Games\Blot\Engine;

use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Contracts\HandlesTimeouts;
use App\Gameplay\Contracts\PausesBetweenRounds;
use App\Gameplay\Contracts\PlaysInTeams;
use App\Gameplay\Contracts\ValidatesActions;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Engine\Transition;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\RandomSource;

/**
 * Armenian Bazar Blot for four players in two partnerships (seats 0 and 2 against 1 and 3), scored in sai
 * to `target` (301). The rules, with their sources and the choices made where sources differ, are in
 * docs/games/blot-rules.md; this class follows that document.
 *
 * Phases: bidding → answer (after Quansh: each player of the contract pair in turn may say Sur — ×4 — or
 * pass) → playing → over (a pause showing the deal's result) → the next deal. A kaput contract stops as
 * soon as the defenders take a trick ("kaput_failed"). Actions:
 * - bidding: bid {value 8–24, suit S|H|D|C|N (no-trump)} (described as a range), kaput {suit}, pass, contra;
 * - answer: recontra, pass;
 * - playing: play {card}; before their first card a player may also declare {index} each combination the
 *   server lists for their hand; its cards are shown to the whole table at once.
 *
 * State: dealer, hand_no, phase, hands (hidden), bids, contract {seat, team, value (25 = kaput), suit
 * (null = no-trump), kaput, doubled 0|1|2}, passes, to_act, leader, trick, last_trick, tricks (count per
 * team), points (card points per team), available and declared_index (per seat, hidden), declared (per
 * seat), declarations_shown, played_first (per seat), belote (seat or null), opener (who opened the bazar),
 * log (this deal's public events: bazar calls, the contract, declarations with their cards, blot and reblot,
 * chakhel — trumping a trick of another suit — tricks, the deal's end), scores (sai per team), target,
 * last_hand, pause_ms, result {team, reason}.
 */
final class BlotEngine implements GameEngine, HandlesTimeouts, PausesBetweenRounds, PlaysInTeams, ValidatesActions
{
    public const MIN_BID = 8;

    public const MAX_BID = 24;

    public const KAPUT = 25;

    /** Suit codes in bids: the four suits and N for no-trump. */
    public const BID_SUITS = ['S', 'H', 'D', 'C', 'N'];

    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        $state = [
            'dealer' => $random->int(0, 3),
            'hand_no' => 0,
            'scores' => [0, 0],
            'target' => (int) ($setup->options['target'] ?? 301),
            'last_hand' => null,
            'pause_ms' => (int) ($setup->options['hand_pause_ms'] ?? 6000),
            'result' => null,
        ];

        return $this->deal($state, $random, nextDealer: false);
    }

    public function teamOf(int $seat, int $seats): array
    {
        return [$seat % 2, $seat % 2 + 2];
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
        $contract = $state['contract'];

        if ($state['phase'] === 'answer') {
            return [new Action('recontra'), new Action('pass')];
        }
        if ($state['phase'] === 'bidding') {
            $actions = [];
            if ($contract === null || ! $contract['kaput']) {
                $lowest = ($contract['value'] ?? self::MIN_BID - 1) + 1;
                if ($lowest <= self::MAX_BID) {
                    $actions[] = new Action('bid', ['max' => self::MAX_BID, 'min' => $lowest]);
                }
                foreach (self::BID_SUITS as $suit) {
                    $actions[] = new Action('kaput', ['suit' => $suit]);
                }
            }
            $actions[] = new Action('pass');
            if ($contract !== null && $contract['team'] !== $seat % 2) {
                $actions[] = new Action('contra');
            }

            return $actions;
        }

        $actions = [];
        if (! $state['played_first'][$seat]) {
            foreach ($state['available'][$seat] as $i => $declaration) {
                if (! in_array($i, $state['declared_index'][$seat], true)) {
                    $actions[] = new Action('declare', ['index' => $i]);
                }
            }
        }
        foreach ($this->playable($state, $seat) as $card) {
            $actions[] = new Action('play', ['card' => $card]);
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function isLegal(array $state, int $seat, Action $action): bool
    {
        if ($action->type !== 'bid') {
            return collect($this->legalActions($state, $seat))->contains(fn (Action $legal) => $legal->equals($action));
        }
        $range = collect($this->legalActions($state, $seat))->first(fn (Action $legal) => $legal->type === 'bid');
        $keys = array_keys($action->payload);
        sort($keys);
        $value = $action->payload['value'] ?? null;

        return $range !== null && $keys === ['suit', 'value']
            && is_int($value) && $value >= $range->payload['min'] && $value <= $range->payload['max']
            && in_array($action->payload['suit'], self::BID_SUITS, true);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition
    {
        if (! $this->isLegal($state, $seat, $action)) {
            throw new IllegalAction('That move is not allowed.');
        }

        $event = ['type' => $action->type, 'seat' => $seat, ...$action->payload];
        $events = [$event];
        if ($action->type === 'play') {
            return new Transition($this->play($state, $seat, (string) $action->payload['card'], $events), $events);
        }
        if ($action->type === 'declare') {
            $i = (int) $action->payload['index'];
            $state['declared_index'][$seat][] = $i;
            $declaration = $state['available'][$seat][$i];
            $state['declared'][$seat][] = $declaration;
            // Announced and shown at once to the whole table: exactly the cards that form it (owner's rule).
            $events[0] = ['type' => 'declare', 'seat' => $seat, 'kind' => $declaration['kind'], 'points' => $declaration['points'], 'cards' => $declaration['cards']];
            $state['log'][] = $events[0];

            return new Transition($state, $events);
        }

        $state['bids'][] = $event;
        $state['log'][] = $event;
        if (($action->type === 'bid' || $action->type === 'kaput') && $state['opener'] === null) {
            $state['opener'] = $seat;
        }
        $state['to_act'] = ($seat + 1) % 4;
        switch ($action->type) {
            case 'bid':
            case 'kaput':
                $kaput = $action->type === 'kaput';
                $suit = (string) $action->payload['suit'];
                $state['contract'] = [
                    'seat' => $seat,
                    'team' => $seat % 2,
                    'value' => $kaput ? self::KAPUT : (int) $action->payload['value'],
                    'suit' => $suit === 'N' ? null : $suit,
                    'kaput' => $kaput,
                    'doubled' => 0,
                ];
                $state['passes'] = 0;
                break;
            case 'contra':
                // Quansh ends the bidding; either player of the contract pair may answer with Sur, in turn.
                $state['contract']['doubled'] = 1;
                $state['phase'] = 'answer';
                $state['answers'] = 0;
                break;
            case 'recontra':
                $state['contract']['doubled'] = 2;

                return new Transition($this->startPlay($state), $events);
            case 'pass':
                if ($state['phase'] === 'answer') {
                    // The first of the pair passed: their partner (across the table) may still say Sur.
                    if (($state['answers'] ?? 0) === 0) {
                        $state['answers'] = 1;
                        $state['to_act'] = ($seat + 2) % 4;

                        return new Transition($state, $events);
                    }

                    return new Transition($this->startPlay($state), $events);
                }
                $state['passes']++;
                if ($state['contract'] === null && $state['passes'] === 4) {
                    $events[] = ['type' => 'redeal'];

                    return new Transition($this->deal($state, $random, nextDealer: true), $events);
                }
                if ($state['contract'] !== null && $state['passes'] === 3) {
                    return new Transition($this->startPlay($state), $events);
                }
                break;
        }

        return new Transition($state, $events);
    }

    /**
     * A timed-out player passes, or plays their first playable card (declaring nothing).
     *
     * @param  array<string, mixed>  $state
     */
    public function timeoutAction(array $state, int $seat): Action
    {
        return $state['phase'] === 'playing'
            ? new Action('play', ['card' => $this->playable($state, $seat)[0]])
            : new Action('pass');
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function viewFor(array $state, ?int $seat): array
    {
        $view = $state;
        unset($view['hands'], $view['pause_ms'], $view['available'], $view['declared_index']);
        $view['seat'] = $seat;
        $view['hand'] = $seat === null ? [] : $state['hands'][$seat];
        $view['hand_sizes'] = array_map('count', $state['hands']);
        $view['trump'] = $state['contract']['suit'] ?? null;
        // Blot is announced as the first of its cards is played: until then only its holder knows.
        $holder = $state['belote'];
        $trump = $view['trump'];
        $shown = $holder !== null && $trump !== null
            && (! in_array('K'.$trump, $state['hands'][$holder], true) || ! in_array('Q'.$trump, $state['hands'][$holder], true));
        $view['belote'] = $shown || $holder === $seat ? $holder : null;
        $view['my_declarations'] = $seat === null ? [] : array_map(
            fn (array $d, int $i) => ['index' => $i, 'kind' => $d['kind'], 'points' => $d['points'], 'cards' => $d['cards'], 'declared' => in_array($i, $state['declared_index'][$seat], true)],
            $state['available'][$seat],
            array_keys($state['available'][$seat]),
        );
        // Announced declarations, with the cards that form them, for everyone (and only those cards).
        $view['declared'] = array_map(
            fn (array $list) => array_map(fn (array $d) => ['kind' => $d['kind'], 'points' => $d['points'], 'cards' => $d['cards']], $list),
            $state['declared'],
        );

        return $view;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function outcome(array $state): ?Outcome
    {
        if ($state['result'] === null) {
            return null;
        }
        $team = $state['result']['team'];

        return new Outcome($this->teamOf($team, 4), $state['result']['reason'], array_map(fn (int $seat) => $state['scores'][$seat % 2], range(0, 3)));
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
        $state = $this->deal($state, $random, nextDealer: true);

        return new Transition($state, [['type' => 'next_hand', 'hand' => $state['hand_no']]]);
    }

    /**
     * The cards $seat may play now.
     *
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    public function playable(array $state, int $seat): array
    {
        $hand = $state['hands'][$seat];
        if ($state['trick'] === []) {
            return $hand;
        }
        $trump = $state['contract']['suit'];
        $played = array_column($state['trick'], 'card');
        $led = $played[0][1];

        $follow = array_values(array_filter($hand, fn (string $c) => $c[1] === $led));
        if ($follow !== []) {
            return $follow;
        }
        if ($trump === null) {
            return $hand;
        }
        $winner = $state['trick'][BlotCards::winning($played, $trump)]['seat'];
        if ($winner % 2 === $seat % 2) {
            return $hand;    // the partner is winning: anything goes
        }
        $trumps = array_values(array_filter($hand, fn (string $c) => $c[1] === $trump));
        if ($trumps === []) {
            return $hand;
        }
        $highest = max(array_map(fn (string $c) => BlotCards::strength($c, $led, $trump), $played));
        $beating = array_values(array_filter($trumps, fn (string $c) => BlotCards::strength($c, $led, $trump) > $highest));

        return $beating ?: $trumps;
    }

    /**
     * Shuffle and deal eight each, then open the bidding left of the dealer.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function deal(array $state, RandomSource $random, bool $nextDealer): array
    {
        if ($nextDealer) {
            $state['dealer'] = ($state['dealer'] + 1) % 4;
        }
        $deck = BlotCards::shuffled($random);
        $first = ($state['dealer'] + 1) % 4;

        return [
            ...$state,
            'hand_no' => $state['hand_no'] + 1,
            'phase' => 'bidding',
            'hands' => array_map(fn (int $i) => array_slice($deck, $i * 8, 8), range(0, 3)),
            'bids' => [],
            'contract' => null,
            'passes' => 0,
            'to_act' => $first,
            'leader' => $first,
            'trick' => [],
            'last_trick' => null,
            'tricks' => [0, 0],
            'points' => [0, 0],
            'available' => [[], [], [], []],
            'declared' => [[], [], [], []],
            'declared_index' => [[], [], [], []],
            'declarations_shown' => false,
            'played_first' => [false, false, false, false],
            'belote' => null,
            'opener' => null,
            'log' => [],
        ];
    }

    /**
     * The bidding is over: work out what each hand may declare, and lead.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function startPlay(array $state): array
    {
        $trump = $state['contract']['suit'];
        $state['phase'] = 'playing';
        $state['available'] = array_map(fn (array $hand) => BlotCards::declarations($hand, $trump), $state['hands']);
        if ($trump !== null) {
            foreach ($state['hands'] as $seat => $hand) {
                if (in_array('K'.$trump, $hand, true) && in_array('Q'.$trump, $hand, true)) {
                    $state['belote'] = $seat;
                }
            }
        }
        $state['to_act'] = $state['leader'];
        $state['log'][] = ['type' => 'contract', ...$state['contract']];

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function play(array $state, int $seat, string $card, array &$events): array
    {
        $trump = $state['contract']['suit'];
        // Blot as the first of the trump king and queen is played, Reblot as the second.
        if ($seat === $state['belote'] && $trump !== null && ($card === 'K'.$trump || $card === 'Q'.$trump)) {
            $other = $card === 'K'.$trump ? 'Q'.$trump : 'K'.$trump;
            $call = in_array($other, $state['hands'][$seat], true) ? 'blot' : 'reblot';
            $events[] = ['type' => $call, 'seat' => $seat, 'card' => $card];
            $state['log'][] = ['type' => $call, 'seat' => $seat, 'card' => $card];
        }
        // Chakhel: trumping a trick of another suit (the player has none of it).
        if ($trump !== null && $state['trick'] !== [] && $card[1] === $trump && $state['trick'][0]['card'][1] !== $trump) {
            $events[] = ['type' => 'chakhel', 'seat' => $seat, 'card' => $card];
            $state['log'][] = ['type' => 'chakhel', 'seat' => $seat, 'card' => $card];
        }
        $state['played_first'][$seat] = true;
        $state['hands'][$seat] = array_values(array_diff($state['hands'][$seat], [$card]));
        $state['trick'][] = ['seat' => $seat, 'card' => $card];
        if (count($state['trick']) < 4) {
            $state['to_act'] = ($seat + 1) % 4;

            return $state;
        }

        $cards = array_column($state['trick'], 'card');
        $winner = $state['trick'][BlotCards::winning($cards, $trump)]['seat'];
        $points = array_sum(array_map(fn (string $c) => BlotCards::points($c, $trump), $cards));
        $last = $state['hands'][$winner] === [];
        if ($last) {
            $points += 10;
        }
        $state['points'][$winner % 2] += $points;
        $state['tricks'][$winner % 2]++;
        $state['last_trick'] = ['cards' => $state['trick'], 'winner' => $winner, 'points' => $points];
        $state['trick'] = [];
        $state['declarations_shown'] = true;
        $state['to_act'] = $winner;
        $events[] = ['type' => 'trick', 'winner' => $winner, 'points' => $points];
        $state['log'][] = ['type' => 'trick', 'seat' => $winner, 'points' => $points, 'last' => $last];

        // A kaput contract is lost with the first trick the defenders take: the deal stops there.
        if (! $last && $state['contract']['kaput'] && $winner % 2 !== $state['contract']['team']) {
            $events[] = ['type' => 'kaput_failed', 'seat' => $winner];
            $state['log'][] = ['type' => 'kaput_failed', 'seat' => $winner];

            return $this->finishHand($state, $events, stoppedEarly: true);
        }

        return $last ? $this->finishHand($state, $events) : $state;
    }

    /**
     * Score the deal in sai (docs/games/blot-rules.md, "Scoring a deal"), add it to the match, then pause
     * before the next deal — or end the match.
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function finishHand(array $state, array &$events, bool $stoppedEarly = false): array
    {
        $contract = $state['contract'];
        $bidders = $contract['team'];
        $defenders = 1 - $bidders;
        $trump = $contract['suit'];

        // Declarations: only the pair with the best announced one scores, and then all of its own.
        $best = null;
        foreach (range(0, 3) as $i) {
            $seat = ($state['leader'] + $i) % 4;
            foreach ($state['declared'][$seat] as $declaration) {
                if ($best === null || $declaration['key'] > $best['key']) {
                    $best = ['key' => $declaration['key'], 'team' => $seat % 2];
                }
            }
        }
        $declPoints = [0, 0];
        if ($best !== null) {
            foreach ($state['declared'] as $seat => $list) {
                if ($seat % 2 === $best['team']) {
                    $declPoints[$best['team']] += array_sum(array_column($list, 'points'));
                }
            }
        }
        $belotePoints = [0, 0];
        if ($state['belote'] !== null) {
            $belotePoints[$state['belote'] % 2] = 20;
        }

        $allTricks = $state['tricks'][$bidders] === 8;
        // Taking no trick only counts when the deal was played out (not a kaput stopped at its first lost trick).
        $noTricks = ! $stoppedEarly && $state['tricks'][$bidders] === 0;
        $made = $contract['kaput']
            ? $allTricks
            : $state['points'][$bidders] + $declPoints[$bidders] + $belotePoints[$bidders] >= $contract['value'] * 10;
        $multiplier = ($trump === null ? [2, 3, 5] : [1, 2, 4])[$contract['doubled']];
        $bid = $contract['value'] * $multiplier;
        $declSai = [intdiv($declPoints[0], 10), intdiv($declPoints[1], 10)];
        $cardSai = [BlotCards::sai($state['points'][0]), BlotCards::sai($state['points'][1])];

        $scored = [0, 0];
        if ($made && $contract['doubled'] === 0) {
            $scored[$bidders] = $bid + ($allTricks ? 25 : 16 - $cardSai[$defenders]) + $declSai[$bidders];
            $scored[$defenders] = ($allTricks ? 0 : $cardSai[$defenders]) + $declSai[$defenders];
        } elseif ($made) {
            $scored[$bidders] = $bid + ($allTricks ? 25 : 16) + $declSai[0] + $declSai[1];
        } else {
            $scored[$defenders] = $bid + ($noTricks ? 25 : 16) + $declSai[0] + $declSai[1];
        }
        $scored[0] += intdiv($belotePoints[0], 10);
        $scored[1] += intdiv($belotePoints[1], 10);
        $state['scores'][0] += $scored[0];
        $state['scores'][1] += $scored[1];

        $declarations = [];
        foreach ($state['declared'] as $seat => $list) {
            foreach ($list as $declaration) {
                $declarations[] = ['seat' => $seat, 'kind' => $declaration['kind'], 'points' => $declaration['points'], 'cards' => $declaration['cards'], 'counted' => $best !== null && $seat % 2 === $best['team']];
            }
        }
        $state['last_hand'] = [
            'number' => $state['hand_no'],
            'contract' => $contract,
            'made' => $made,
            'tricks' => $state['tricks'],
            'card_points' => $state['points'],
            'card_sai' => $cardSai,
            'declarations' => $declarations,
            'declaration_sai' => $declSai,
            'belote_sai' => [intdiv($belotePoints[0], 10), intdiv($belotePoints[1], 10)],
            'bid_sai' => $bid,
            'scored' => $scored,
            'totals' => $state['scores'],
            'kaput' => $allTricks || $state['tricks'][$defenders] === 8,
            'stopped_early' => $stoppedEarly,
        ];
        $events[] = ['type' => 'hand_over', 'made' => $made, 'scored' => $scored];
        $state['log'][] = ['type' => 'hand_over', 'made' => $made, 'scored' => $scored, 'kaput' => $allTricks, 'reverse_kaput' => $noTricks];
        $state['phase'] = 'over';
        $state['to_act'] = null;

        [$a, $b] = $state['scores'];
        $state['result'] = match (true) {
            // The bidders took no trick at all: they lose the match.
            $noTricks => ['team' => $defenders, 'reason' => 'reverse_kaput'],
            // A contra'd kaput that is made wins the match.
            $contract['kaput'] && $contract['doubled'] > 0 && $made => ['team' => $bidders, 'reason' => 'kaput_contra'],
            max($a, $b) >= $state['target'] && $a !== $b => ['team' => $a > $b ? 0 : 1, 'reason' => 'target_reached'],
            default => null,
        };

        return $state;
    }
}
