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
 * Bazar blot for four players in two partnerships (seats 0 and 2 against 1 and 3), to `target` points
 * (301). 32 cards, eight each.
 *
 * Bidding (the bazar) starts left of the dealer: bid {value, suit} (value × 10 points, 8–30, higher than
 * the last bid), pass, double (an opponent of the bidder; nobody bids higher after it) or redouble (the
 * doubled team, which ends the bidding). Three passes after a bid or a double end it; four passes without
 * a bid redeal. The highest bid makes its
 * suit trump.
 *
 * Play: the player left of the dealer leads. Follow suit; when trumps are led, beat the highest trump if
 * you can. Without the led suit you must trump (beating a trump already played if you can, otherwise any
 * trump), unless your partner is winning the trick. Trumps rank J 9 A 10 K Q 8 7 (20, 14, 11, 10, 4, 3);
 * other suits A 10 K Q J 9 8 7 (11, 10, 4, 3, 2). The last trick adds 10: 162 in all; all eight tricks
 * (kaput) count 250.
 *
 * Declarations are found by the engine and shown after the first trick: sequences (three 20, four 50,
 * five 100) and four of a kind (jacks 200, nines 150, A/10/K/Q 100). Only the team with the best single
 * declaration scores its declarations (a tie goes to the team that plays first). Belote — king and queen
 * of trumps in one hand — is 20 for its holder's team.
 *
 * Scoring: the bidding team needs at least its bid (tricks + declarations + belote). Made: each team
 * scores its own points; failed: the defenders score 162 (or 250) and every declaration. A doubled hand
 * goes wholly (162 + every declaration) to the team that wins it, ×2, redoubled ×4. Belote always stays
 * with its holder. A team at `target` or more after a hand wins (the higher score; a tie plays on).
 *
 * State: dealer, hand_no, phase (bidding|playing|over), hands (hidden), bids, contract {seat, team,
 * value, suit, doubled}, passes, to_act, leader, trick [{seat, card}], last_trick, tricks [per team],
 * points [per team], declarations (per seat), declarations_shown, belote (seat or null), scores, target,
 * last_hand, pause_ms, result.
 */
final class BlotEngine implements GameEngine, HandlesTimeouts, PausesBetweenRounds, PlaysInTeams, ValidatesActions
{
    public const MIN_BID = 8;

    public const MAX_BID = 30;

    private const KAPUT = 250;

    public function initialState(GameSetup $setup, RandomSource $random): array
    {
        $state = [
            'dealer' => $random->int(0, 3),
            'hand_no' => 0,
            'scores' => [0, 0],
            'target' => (int) ($setup->options['target'] ?? 301),
            'last_hand' => null,
            'pause_ms' => (int) ($setup->options['hand_pause_ms'] ?? 5000),
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
        if ($state['phase'] === 'playing') {
            return array_map(fn (string $card) => new Action('play', ['card' => $card]), $this->playable($state, $seat));
        }

        $contract = $state['contract'];
        $actions = [];
        $lowest = ($contract['value'] ?? self::MIN_BID - 1) + 1;
        if ($lowest <= self::MAX_BID && ($contract['doubled'] ?? 0) === 0) {
            $actions[] = new Action('bid', ['max' => self::MAX_BID, 'min' => $lowest]);
        }
        $actions[] = new Action('pass');
        if ($contract !== null && $contract['doubled'] === 0 && $contract['team'] !== $seat % 2) {
            $actions[] = new Action('double');
        }
        if ($contract !== null && $contract['doubled'] === 1 && $contract['team'] === $seat % 2) {
            $actions[] = new Action('redouble');
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
        $value = $action->payload['value'] ?? null;
        $keys = array_keys($action->payload);
        sort($keys);

        return $range !== null
            && $keys === ['suit', 'value']
            && is_int($value) && $value >= $range->payload['min'] && $value <= $range->payload['max']
            && in_array($action->payload['suit'], BlotCards::SUITS, true);
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

        $state['bids'][] = $event;
        $state['to_act'] = ($seat + 1) % 4;
        switch ($action->type) {
            case 'bid':
                $state['contract'] = ['seat' => $seat, 'team' => $seat % 2, 'value' => (int) $action->payload['value'], 'suit' => (string) $action->payload['suit'], 'doubled' => 0];
                $state['passes'] = 0;
                break;
            case 'double':
                $state['contract']['doubled'] = 1;
                $state['passes'] = 0;
                break;
            case 'redouble':
                $state['contract']['doubled'] = 2;

                return new Transition($this->startPlay($state), $events);
            case 'pass':
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
     * A timed-out player passes, or plays their first playable card.
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
        unset($view['hands'], $view['pause_ms']);
        $view['seat'] = $seat;
        $view['hand'] = $seat === null ? [] : $state['hands'][$seat];
        $view['hand_sizes'] = array_map('count', $state['hands']);
        $view['trump'] = $state['contract']['suit'] ?? null;
        // Declarations are announced at the first trick and shown once it is over; your own you always see.
        $view['declarations'] = array_map(
            fn (array $mine, int $s) => $state['declarations_shown'] || $s === $seat ? $mine : [],
            $state['declarations'],
            array_keys($state['declarations']),
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

        return new Outcome($this->teamOf($team, 4), 'target_reached', array_map(fn (int $seat) => $state['scores'][$seat % 2], range(0, 3)));
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
        $highestTrump = max(array_map(fn (string $c) => $c[1] === $trump ? BlotCards::strength($c, $led, $trump) : -1, $played));
        $beats = fn (array $cards) => array_values(array_filter($cards, fn (string $c) => BlotCards::strength($c, $led, $trump) > $highestTrump));

        $follow = array_values(array_filter($hand, fn (string $c) => $c[1] === $led));
        if ($follow !== []) {
            // Following trumps: beat the highest trump if you can.
            return $led === $trump ? ($beats($follow) ?: $follow) : $follow;
        }

        $winner = $state['trick'][BlotCards::winning($played, $trump)]['seat'];
        if ($winner % 2 === $seat % 2) {
            return $hand;    // the partner is winning: anything goes
        }
        $trumps = array_values(array_filter($hand, fn (string $c) => $c[1] === $trump));
        if ($trumps === []) {
            return $hand;
        }

        return $beats($trumps) ?: $trumps;
    }

    /**
     * Shuffle and deal eight each, then open the bidding.
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
            'declarations' => [[], [], [], []],
            'declarations_shown' => false,
            'belote' => null,
        ];
    }

    /**
     * The bidding is over: find everyone's declarations and lead.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function startPlay(array $state): array
    {
        $trump = $state['contract']['suit'];
        $state['phase'] = 'playing';
        $state['declarations'] = array_map(fn (array $hand) => BlotCards::declarations($hand), $state['hands']);
        foreach ($state['hands'] as $seat => $hand) {
            if (in_array('K'.$trump, $hand, true) && in_array('Q'.$trump, $hand, true)) {
                $state['belote'] = $seat;
            }
        }
        $state['to_act'] = $state['leader'];

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function play(array $state, int $seat, string $card, array &$events): array
    {
        $state['hands'][$seat] = array_values(array_diff($state['hands'][$seat], [$card]));
        $state['trick'][] = ['seat' => $seat, 'card' => $card];
        if (count($state['trick']) < 4) {
            $state['to_act'] = ($seat + 1) % 4;

            return $state;
        }

        $trump = $state['contract']['suit'];
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

        return $last ? $this->finishHand($state, $events) : $state;
    }

    /**
     * Score the hand, then pause before the next deal (or end the game).
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function finishHand(array $state, array &$events): array
    {
        $contract = $state['contract'];
        $bidders = $contract['team'];
        $defenders = 1 - $bidders;

        // Kaput: one team took every trick.
        $tricks = $state['points'];
        foreach ([0, 1] as $team) {
            if ($state['tricks'][$team] === 8) {
                $tricks = [$team => self::KAPUT, 1 - $team => 0];
            }
        }
        ksort($tricks);
        $allTricks = $tricks[0] + $tricks[1];

        // Only the team with the best single declaration scores its declarations.
        $declared = [0, 0];
        $best = null;
        foreach (range(0, 3) as $i) {
            $seat = ($state['leader'] + $i) % 4;
            foreach ($state['declarations'][$seat] as $declaration) {
                $declared[$seat % 2] += $declaration['points'];
                if ($best === null || $declaration['rank'] > $best['rank']) {
                    $best = ['rank' => $declaration['rank'], 'team' => $seat % 2];
                }
            }
        }
        $declarations = [0, 0];
        if ($best !== null) {
            $declarations[$best['team']] = $declared[$best['team']];
        }
        $belote = [0, 0];
        if ($state['belote'] !== null) {
            $belote[$state['belote'] % 2] = 20;
        }

        $total = fn (int $team) => $tricks[$team] + $declarations[$team] + $belote[$team];
        $made = $total($bidders) >= $contract['value'] * 10;
        $multiplier = [1, 2, 4][$contract['doubled']];
        $scored = [0, 0];
        if ($contract['doubled'] === 0 && $made) {
            $scored = [$total(0), $total(1)];
        } else {
            // A failed contract, or any doubled one: the winning team takes everything.
            $taker = $made ? $bidders : $defenders;
            $scored[$taker] = ($allTricks + $declarations[0] + $declarations[1]) * $multiplier + $belote[$taker];
            $scored[1 - $taker] = $belote[1 - $taker];
        }
        $state['scores'][0] += $scored[0];
        $state['scores'][1] += $scored[1];

        $state['last_hand'] = [
            'number' => $state['hand_no'],
            'contract' => $contract,
            'made' => $made,
            'tricks' => $tricks,
            'declarations' => $declarations,
            'belote' => $belote,
            'scored' => $scored,
            'kaput' => in_array(self::KAPUT, $tricks, true),
        ];
        $events[] = ['type' => 'hand_over', 'made' => $made, 'scored' => $scored];
        $state['phase'] = 'over';
        $state['to_act'] = null;

        [$a, $b] = $state['scores'];
        if (max($a, $b) >= $state['target'] && $a !== $b) {
            $state['result'] = ['team' => $a > $b ? 0 : 1];
        }

        return $state;
    }
}
