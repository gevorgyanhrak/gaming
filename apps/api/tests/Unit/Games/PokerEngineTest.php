<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\SecureRandom;
use App\Games\Poker\Bot\PokerBot;
use App\Games\Poker\Engine\PokerEngine;
use App\Games\Poker\Engine\PokerHands;

function holdem(int $seats = 2, array $options = []): array
{
    return (new PokerEngine)->initialState(new GameSetup($seats, ['starting_chips' => 1000, 'small_blind' => 5, 'big_blind' => 10, ...$options]), new SecureRandom);
}

function holdemAct(array $state, string $type, ?int $amount = null): array
{
    $action = new Action($type, $amount === null ? [] : ['amount' => $amount]);

    return (new PokerEngine)->apply($state, $state['to_act'], $action, new SecureRandom)->state;
}

/** Deal the next hand once the pause after a hand is over. */
function holdemNext(array $state): array
{
    return (new PokerEngine)->resume($state, new SecureRandom)->state;
}

/** Total chips: stacks, the pot and chips of players who left. */
function holdemChips(array $state): int
{
    return array_sum($state['stacks']) + array_sum($state['committed']) + $state['forfeited'];
}

/** A hand on the river, everyone still to act, with fixed cards. */
function riverSpot(array $hole, array $board, array $committed, array $stacks, int $button = 0): array
{
    $state = holdem(count($hole));
    $n = count($hole);

    return [...$state,
        'hole' => $hole, 'board' => $board, 'street' => 'river', 'button' => $button,
        'committed' => $committed, 'stacks' => $stacks, 'bets' => array_fill(0, $n, 0),
        'folded' => array_fill(0, $n, false), 'acted' => array_fill(0, $n, false), 'out' => array_fill(0, $n, false),
        'current_bet' => 0, 'to_act' => ($button + 1) % $n,
    ];
}

it('ranks poker hands', function () {
    $score = fn (string $cards) => PokerHands::best(explode(' ', $cards));
    $order = [
        'AS KS QS JS TS 2D 3C',   // straight flush
        '9C 9D 9H 9S KD 2C 3D',   // four of a kind
        'KC KD KH 2S 2D 7C 8D',   // full house
        'AH 9H 7H 4H 2H KS QD',   // flush
        '6C 5D 4H 3S 2D KC KD',   // straight, six high
        'AC 2D 3H 4S 5D KC QD',   // the wheel
        'QC QD QH 9S 2D 7C 4D',   // three of a kind
        'JC JD 4H 4S AD 7C 2D',   // two pair, ace kicker
        'JC JD 4H 4S KD 7C 2D',   // two pair, king kicker
        'TC TD AH 8S 6D 3C 2D',   // pair
        'AC KD 9H 7S 5D 3C 2D',   // high card
    ];
    $scores = array_map(fn (string $hand) => $score($hand)[0], $order);

    expect($scores)->toBe(array_values(collect($scores)->sortDesc()->all()))
        ->and(count(array_unique($scores)))->toBe(count($scores))
        ->and(array_map(fn (string $hand) => $score($hand)[1], [$order[0], $order[5], $order[7]]))->toBe(['straight_flush', 'straight', 'two_pair'])
        // The board plays for both: a split.
        ->and($score('2C 3D AS KS QS JS TS')[0])->toBe($score('4C 5D AS KS QS JS TS')[0]);
});

it('posts the blinds heads-up with the button on the small blind, acting first', function () {
    $state = holdem();
    $button = $state['button'];

    expect($state['bets'][$button])->toBe(5)
        ->and($state['bets'][1 - $button])->toBe(10)
        ->and($state['to_act'])->toBe($button)
        ->and($state['hole'][0])->toHaveCount(2)
        ->and($state['deck'])->toHaveCount(48)
        ->and(holdemChips($state))->toBe(2000);
});

it('seats the blinds left of the button with three or more players', function () {
    $state = holdem(4);
    $b = $state['button'];

    expect($state['bets'][($b + 1) % 4])->toBe(5)
        ->and($state['bets'][($b + 2) % 4])->toBe(10)
        ->and($state['to_act'])->toBe(($b + 3) % 4);
});

it('shows only your own cards', function () {
    $engine = new PokerEngine;
    $state = holdem(3);
    $view = $engine->viewFor($state, 0);
    $json = json_encode($view);

    expect($view['hand_cards'])->toBe($state['hole'][0])
        ->and($view)->not->toHaveKeys(['deck', 'hole']);
    foreach ([...$state['hole'][1], ...$state['hole'][2], ...$state['deck']] as $card) {
        expect($json)->not->toContain('"'.$card.'"');
    }
});

it('accepts raises from the minimum up to all-in', function () {
    $engine = new PokerEngine;
    $state = holdem();
    $seat = $state['to_act'];

    expect($engine->legalActions($state, $seat))->toEqual([new Action('fold'), new Action('call'), new Action('raise', ['max' => 1000, 'min' => 20])])
        ->and($engine->isLegal($state, $seat, new Action('raise', ['amount' => 19])))->toBeFalse()
        ->and($engine->isLegal($state, $seat, new Action('raise', ['amount' => 20])))->toBeTrue()
        ->and($engine->isLegal($state, $seat, new Action('raise', ['amount' => 1001])))->toBeFalse()
        ->and($engine->isLegal($state, $seat, new Action('raise', ['amount' => '50'])))->toBeFalse();

    $state = holdemAct($state, 'raise', 60);    // raise by 50
    expect($engine->legalActions($state, $state['to_act'])[2]->payload['min'])->toBe(110);
});

it('deals the flop when the betting round closes, the big blind first to act after it', function () {
    $state = holdem();
    $button = $state['button'];
    $state = holdemAct($state, 'call');
    expect($state['street'])->toBe('preflop')->and($state['to_act'])->toBe(1 - $button);    // the big blind's option

    $state = holdemAct($state, 'check');
    expect($state['street'])->toBe('flop')
        ->and($state['board'])->toHaveCount(3)
        ->and($state['to_act'])->toBe(1 - $button)
        ->and(holdemChips($state))->toBe(2000);
});

it('gives an uncontested pot to the last player, pauses, then deals the next hand', function () {
    $engine = new PokerEngine;
    $state = holdem();
    $button = $state['button'];
    $board = $state['board'];
    $state = holdemAct($state, 'fold');

    expect($state['street'])->toBe('over')
        ->and($engine->seatsToAct($state))->toBe([])
        ->and($engine->outcome($state))->toBeNull()
        ->and($engine->pauseMs($state))->toBe(3000)
        ->and($state['hand'])->toBe(1)
        ->and($state['board'])->toBe($board)
        ->and($state['last_hand']['pots'])->toBe([['amount' => 15, 'winners' => [1 - $button], 'hand' => null]])
        ->and($state['last_hand']['payouts'][1 - $button])->toBe(15)
        ->and($state['last_hand']['payouts'][$button])->toBe(0)
        ->and(holdemChips($state))->toBe(2000);

    $transition = $engine->resume($state, new SecureRandom);
    $state = $transition->state;
    expect($state['hand'])->toBe(2)
        ->and($state['street'])->toBe('preflop')
        ->and($state['button'])->toBe(1 - $button)
        ->and($engine->pauseMs($state))->toBeNull()
        ->and($engine->seatsToAct($state))->toHaveCount(1)
        ->and($transition->events)->toBe([['type' => 'next_hand', 'hand' => 2]])
        ->and(holdemChips($state))->toBe(2000);
    expect(fn () => $engine->resume($state, new SecureRandom))->toThrow(IllegalAction::class);
});

it('pauses longer after a showdown and uses the mode\'s pause lengths', function () {
    $state = riverSpot([['AS', 'AD'], ['KS', 'KD']], ['2C', '7H', '9D', 'JC', '3S'], [50, 50], [950, 950]);
    $state = holdemAct(holdemAct($state, 'check'), 'check');
    expect((new PokerEngine)->pauseMs($state))->toBe(5000);

    $quick = holdemAct(holdem(2, ['fold_pause_ms' => 1500, 'showdown_pause_ms' => 4000]), 'fold');
    expect((new PokerEngine)->pauseMs($quick))->toBe(1500)
        ->and((new PokerEngine)->viewFor($quick, 0))->not->toHaveKey('pause_ms');
});

it('ends the game without a pause when the hand leaves one player with chips', function () {
    // Both all-in on the river: the first check runs the hand to showdown.
    $state = riverSpot([['AS', 'AD'], ['KS', 'KD']], ['2C', '7H', '9D', 'JC', '3S'], [1000, 1000], [0, 0]);
    $state = holdemAct($state, 'check');
    $engine = new PokerEngine;

    expect($engine->outcome($state)?->winners)->toBe([0])
        ->and($engine->pauseMs($state))->toBeNull()
        ->and($state['last_hand']['payouts'])->toBe([2000, 0]);
});

it('ends the game when a player leaves during the pause and only one is left', function () {
    $engine = new PokerEngine;
    $state = holdemAct(holdem(3), 'fold');
    $state = holdemAct($state, 'fold');
    expect($state['street'])->toBe('over');

    $leaver = $state['last_hand']['pots'][0]['winners'][0];
    $state = $engine->depart($state, $leaver, new SecureRandom)->state;
    expect($engine->outcome($state))->toBeNull()
        ->and($engine->pauseMs($state))->not->toBeNull()
        ->and(holdemChips($state))->toBe(3000);

    $state = $engine->depart($state, ($leaver + 1) % 3, new SecureRandom)->state;
    expect($engine->outcome($state)?->winners)->toBe([($leaver + 2) % 3])
        ->and($engine->pauseMs($state))->toBeNull();
});

it('splits main and side pots at showdown', function () {
    // Seat 0 is all-in for 100 with the best hand; seat 1 beats seat 2 for the side pot.
    $state = riverSpot(
        [['AS', 'AD'], ['KS', 'KD'], ['QS', 'QD']],
        ['2C', '7H', '9D', 'JC', '3S'],
        [100, 300, 300],
        [0, 700, 700],
    );
    $state = holdemAct(holdemAct($state, 'check'), 'check');

    expect($state['last_hand']['pots'])->toBe([
        ['amount' => 300, 'winners' => [0], 'hand' => 'pair'],
        ['amount' => 400, 'winners' => [1], 'hand' => 'pair'],
    ])
        ->and($state['last_hand']['payouts'])->toBe([300, 400, 0])
        ->and($state['last_hand']['shown'])->toHaveCount(3)
        ->and(holdemChips($state))->toBe(2100);
});

it('splits a tied pot, the odd chip going left of the button', function () {
    $state = riverSpot(
        [['2C', '3D'], ['4C', '5D'], ['KH', 'KC']],
        ['AS', 'KS', 'QS', 'JS', 'TS'],
        [55, 55, 55],
        [945, 945, 945],
        button: 2,
    );
    $state = holdemAct(holdemAct(holdemAct($state, 'check'), 'check'), 'check');

    expect($state['last_hand']['pots'])->toBe([['amount' => 165, 'winners' => [0, 1, 2], 'hand' => 'straight_flush']])
        ->and($state['last_hand']['payouts'])->toBe([55, 55, 55]);
});

it('doubles the blinds every level', function () {
    $state = holdem(2, ['level_hands' => 2]);
    foreach (range(1, 2) as $hand) {
        $state = holdemNext(holdemAct($state, 'fold'));
    }

    expect($state['hand'])->toBe(3)->and($state['blinds'])->toBe([10, 20]);
});

it('checks or folds for a player who runs out of time', function () {
    $engine = new PokerEngine;
    $state = holdem();

    expect($engine->timeoutAction($state, $state['to_act']))->toEqual(new Action('fold'));
    $state = holdemAct($state, 'call');
    expect($engine->timeoutAction($state, $state['to_act']))->toEqual(new Action('check'));
});

it('takes a leaving player out and plays on', function () {
    $engine = new PokerEngine;
    $state = holdem(3);
    $leaver = $state['to_act'];

    $state = $engine->depart($state, $leaver, new SecureRandom)->state;
    expect($state['out'][$leaver])->toBeTrue()
        ->and($state['to_act'])->not->toBe($leaver)
        ->and($engine->outcome($state))->toBeNull()
        ->and(holdemChips($state))->toBe(3000);

    $state = $engine->depart($state, $state['to_act'], new SecureRandom)->state;
    expect($engine->outcome($state)?->reason)->toBe('last_standing')
        ->and($engine->outcome($state)?->winners)->toHaveCount(1);
});

it('plays random sit-and-gos to the end without losing a chip', function (int $seats) {
    $engine = new PokerEngine;
    $random = new SecureRandom;

    foreach (range(1, 4) as $game) {
        $state = holdem($seats, ['level_hands' => 5]);
        for ($i = 0; $i < 5000 && $engine->outcome($state) === null; $i++) {
            if ($engine->pauseMs($state) !== null) {
                $state = $engine->resume($state, $random)->state;

                continue;
            }
            $seat = $state['to_act'];
            $legal = $engine->legalActions($state, $seat);
            expect($legal)->not->toBeEmpty();
            $action = $legal[$random->int(0, count($legal) - 1)];
            if ($action->type === 'raise') {
                $action = new Action('raise', ['amount' => $random->int($action->payload['min'], $action->payload['max'])]);
            }
            $state = $engine->apply($state, $seat, $action, $random)->state;
            expect(holdemChips($state))->toBe(1000 * $seats);
        }

        $outcome = $engine->outcome($state);
        expect($outcome)->not->toBeNull()
            ->and($state['stacks'][$outcome->winners[0]])->toBe(1000 * $seats);
    }
})->with([2, 3, 6]);

it('never puts hidden cards in the events every player receives', function () {
    $engine = new PokerEngine;
    $random = new SecureRandom;
    $state = holdem(4, ['level_hands' => 3]);

    for ($i = 0; $i < 400 && $engine->outcome($state) === null; $i++) {
        if ($engine->pauseMs($state) !== null) {
            $state = $engine->resume($state, $random)->state;

            continue;
        }
        $seat = $state['to_act'];
        $legal = $engine->legalActions($state, $seat);
        $action = $legal[$random->int(0, count($legal) - 1)];
        if ($action->type === 'raise') {
            $action = new Action('raise', ['amount' => $action->payload['min']]);
        }
        $transition = $engine->apply($state, $seat, $action, $random);
        $holes = array_merge(...$state['hole']);
        foreach ($transition->events as $event) {
            if ($event['type'] === 'dealt') {
                // Community cards are public; they never come from anyone's hand.
                expect(array_intersect($event['cards'], $holes))->toBe([]);
            } else {
                foreach (PokerHands::deck() as $card) {
                    expect(json_encode($event))->not->toContain('"'.$card.'"');
                }
            }
        }
        $state = $transition->state;
    }
});

it('has bots act legally and the medium bot beat loose play heads-up', function () {
    $engine = new PokerEngine;
    $bot = new PokerBot;
    $random = new SecureRandom;
    $wins = 0;

    foreach (range(1, 8) as $game) {
        $mediumSeat = $game % 2;
        $state = holdem(2, ['level_hands' => 10]);
        for ($i = 0; $i < 3000 && $engine->outcome($state) === null; $i++) {
            if ($engine->pauseMs($state) !== null) {
                $state = $engine->resume($state, $random)->state;

                continue;
            }
            $seat = $state['to_act'];
            $level = $seat === $mediumSeat ? BotDifficulty::Medium : BotDifficulty::Easy;
            $action = $bot->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), $level, $random);
            expect($engine->isLegal($state, $seat, $action))->toBeTrue();
            $state = $engine->apply($state, $seat, $action, $random)->state;
        }
        $wins += $engine->outcome($state)?->winners === [$mediumSeat] ? 1 : 0;
    }

    // Medium wins about nine games in ten against easy.
    expect($wins)->toBeGreaterThanOrEqual(5);
});

it('has the hard bot act legally at a full table', function () {
    $engine = new PokerEngine;
    $random = new SecureRandom;
    $state = holdem(6);

    foreach (range(1, 30) as $step) {
        if ($engine->outcome($state) !== null) {
            break;
        }
        if ($engine->pauseMs($state) !== null) {
            $state = $engine->resume($state, $random)->state;

            continue;
        }
        $seat = $state['to_act'];
        $action = (new PokerBot)->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), BotDifficulty::Hard, $random);
        expect($engine->isLegal($state, $seat, $action))->toBeTrue();
        $state = $engine->apply($state, $seat, $action, $random)->state;
    }
});
