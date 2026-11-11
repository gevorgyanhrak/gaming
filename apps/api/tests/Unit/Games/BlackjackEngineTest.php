<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\SecureRandom;
use App\Games\Blackjack\Bot\BlackjackBot;
use App\Games\Blackjack\Engine\BlackjackCards;
use App\Games\Blackjack\Engine\BlackjackEngine;

/** A table of $seats with 1,000 chips each, bets 10–500, one deck. */
function blackjack(int $seats = 1, array $options = []): array
{
    return (new BlackjackEngine)->initialState(new GameSetup($seats, ['starting_chips' => 1000, 'table_min_bet' => 10, 'table_max_bet' => 500, 'decks' => 1, 'rounds' => 3, ...$options]), new SecureRandom);
}

/** Put these cards on top of the shoe (deal order: each seat, dealer, each seat, dealer hole, then draws). */
function stackShoe(array $state, array $cards): array
{
    $state['shoe'] = [...$cards, ...array_fill(0, 30, '2C')];

    return $state;
}

function bj(array $state, string $type, ?int $amount = null, ?int $seat = null): array
{
    $action = new Action($type, $amount === null ? [] : ['amount' => $amount]);

    return (new BlackjackEngine)->apply($state, $seat ?? $state['to_act'], $action, new SecureRandom)->state;
}

function types(array $state, int $seat = 0): array
{
    return array_map(fn (Action $a) => $a->type, (new BlackjackEngine)->legalActions($state, $seat));
}

it('starts with the first seat betting within the table limits', function () {
    $state = blackjack(2);

    expect($state['phase'])->toBe('betting')
        ->and($state['round'])->toBe(1)
        ->and((new BlackjackEngine)->seatsToAct($state))->toBe([0])
        ->and((new BlackjackEngine)->legalActions($state, 0)[0]->toArray())->toBe(['type' => 'bet', 'max' => 500, 'min' => 10])
        ->and((new BlackjackEngine)->legalActions($state, 1))->toBe([]);
});

it('limits the maximum bet to the stack and takes the bet from it', function () {
    $state = blackjack(1, ['stacks' => [120]]);
    $engine = new BlackjackEngine;

    expect($engine->isLegal($state, 0, new Action('bet', ['amount' => 121])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('bet', ['amount' => 9])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('bet', ['amount' => '50'])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('bet', ['amount' => 120])))->toBeTrue();

    $state = bj(stackShoe($state, ['TS', '9H', '8D', '7C']), 'bet', 100);
    expect($state['stacks'][0])->toBe(20)
        ->and($engine->chipsOf($state, 0))->toBe(120);
});

it('rejects moves out of turn and unknown actions', function () {
    $state = blackjack(2);

    expect(fn () => bj($state, 'bet', 10, seat: 1))->toThrow(IllegalAction::class)
        ->and(fn () => bj($state, 'hit'))->toThrow(IllegalAction::class);
});

it('pays a blackjack 3 to 2 without asking the player', function () {
    $state = bj(stackShoe(blackjack(), ['AS', '9H', 'KD', '7C']), 'bet', 100);

    expect($state['phase'])->toBe('over')
        ->and($state['hands'][0][0]['result'])->toBe('blackjack')
        ->and($state['stacks'][0])->toBe(1150)
        ->and($state['dealer'])->toBe(['9H', '7C'])     // no player left standing: the dealer doesn't draw
        ->and($state['last_round']['net'])->toBe([150])
        ->and((new BlackjackEngine)->pauseMs($state))->toBe(3500);
});

it('ends the round at once when the dealer peeks a blackjack', function () {
    $state = bj(stackShoe(blackjack(), ['9S', 'AH', '9D', 'KC']), 'bet', 100);

    expect($state['phase'])->toBe('over')
        ->and($state['hole_hidden'])->toBeFalse()
        ->and($state['hands'][0][0]['result'])->toBe('lose')
        ->and($state['stacks'][0])->toBe(900);
});

it('pushes a blackjack against the dealer\'s blackjack', function () {
    $state = bj(stackShoe(blackjack(), ['AS', 'AH', 'KD', 'KC']), 'bet', 100);

    expect($state['hands'][0][0]['result'])->toBe('push')
        ->and($state['stacks'][0])->toBe(1000);
});

it('loses a hand that goes over 21', function () {
    $state = bj(stackShoe(blackjack(), ['TS', '9H', '6D', '8C', 'KH']), 'bet', 100);
    expect(types($state))->toBe(['hit', 'stand', 'double']);

    $state = bj($state, 'hit');

    expect($state['hands'][0][0]['status'])->toBe('bust')
        ->and($state['hands'][0][0]['result'])->toBe('lose')
        ->and($state['dealer'])->toBe(['9H', '8C'])
        ->and($state['stacks'][0])->toBe(900);
});

it('doubles the bet for exactly one more card', function () {
    $state = bj(stackShoe(blackjack(), ['6S', '9H', '5D', '7C', 'TH', 'TC']), 'bet', 100);
    $state = bj($state, 'double');

    expect($state['hands'][0][0])->toMatchArray(['cards' => ['6S', '5D', 'TH'], 'bet' => 200, 'doubled' => true, 'result' => 'win'])
        ->and($state['dealer'])->toBe(['9H', '7C', 'TC'])   // 16 draws and busts
        ->and($state['stacks'][0])->toBe(1200);
});

it('splits a pair into two hands with a second bet', function () {
    $state = bj(stackShoe(blackjack(), ['8S', '9H', '8D', '7C', '3H', '2D', 'TC']), 'bet', 100);
    expect(types($state))->toBe(['hit', 'stand', 'double', 'split']);

    $state = bj($state, 'split');
    expect($state['hands'][0])->toHaveCount(2)
        ->and($state['hands'][0][0]['cards'])->toBe(['8S', '3H'])
        ->and($state['hands'][0][1]['cards'])->toBe(['8D', '2D'])
        ->and($state['stacks'][0])->toBe(800)
        ->and($state['active'])->toBe(0);

    $state = bj($state, 'stand');
    expect($state['active'])->toBe(1);
    $state = bj($state, 'stand');

    // Dealer 16 draws the ten and busts: both hands win.
    expect(array_column($state['hands'][0], 'result'))->toBe(['win', 'win'])
        ->and($state['stacks'][0])->toBe(1200);
});

it('gives split aces one card each, and their 21 is not a blackjack', function () {
    $state = bj(stackShoe(blackjack(), ['AS', '9H', 'AD', '8C', 'KH', '5D']), 'bet', 100);
    $state = bj($state, 'split');

    expect(array_column($state['hands'][0], 'status'))->toBe(['stood', 'stood'])
        ->and(array_column($state['hands'][0], 'result'))->toBe(['win', 'lose'])   // 21 vs 17 wins 1:1; 16 loses
        ->and($state['stacks'][0])->toBe(1000);
});

it('splits ten-value cards but never more than four hands', function () {
    $state = bj(stackShoe(blackjack(), ['KS', '9H', 'QD', '7C', 'JH', 'TD', 'KC', '5S']), 'bet', 10);
    $state = bj($state, 'split');   // K+J, Q+T
    expect(types($state))->toContain('split');
    $state = bj($state, 'split');   // K+K, J+5S, Q+T
    expect($state['hands'][0])->toHaveCount(3);

    $state['hands'][0][] = $state['hands'][0][2];
    expect(types($state))->not->toContain('split');
});

it('stands on soft 17 by default and hits it when the table says so', function () {
    $stand = bj(stackShoe(blackjack(), ['TS', 'AH', '8D', '6C', '4D']), 'bet', 100);
    $stand = bj($stand, 'stand');
    expect($stand['dealer'])->toBe(['AH', '6C']);

    $hit = bj(stackShoe(blackjack(1, ['dealer_hits_soft_17' => true]), ['TS', 'AH', '8D', '6C', '4D']), 'bet', 100);
    $hit = bj($hit, 'stand');
    expect($hit['dealer'])->toBe(['AH', '6C', '4D'])
        ->and($hit['hands'][0][0]['result'])->toBe('lose');   // 18 vs 21
});

it('hides the hole card and the shoe', function () {
    $state = bj(stackShoe(blackjack(), ['TS', '9H', '6D', '8C']), 'bet', 100);
    $view = (new BlackjackEngine)->viewFor($state, 0);

    expect($view['dealer'])->toBe(['9H', null])
        ->and($view['dealer_total'])->toBe(9)
        ->and($view)->not->toHaveKey('shoe')
        ->and($view['hands'][0][0]['total'])->toBe(16)
        ->and($view['seat'])->toBe(0)
        ->and(json_encode($view))->not->toContain('8C');
});

it('lets every seat bet, then plays the hands in seat order', function () {
    $state = blackjack(2);
    $state = bj($state, 'bet', 50);
    expect($state['to_act'])->toBe(1)->and($state['phase'])->toBe('betting');

    $state = bj(stackShoe($state, ['TS', 'TD', '9H', '7S', '8D', '7C']), 'bet', 20);
    expect($state['phase'])->toBe('playing')
        ->and($state['hands'][0][0]['cards'])->toBe(['TS', '7S'])
        ->and($state['hands'][1][0]['cards'])->toBe(['TD', '8D'])
        ->and($state['to_act'])->toBe(0);

    $state = bj(bj($state, 'stand'), 'stand');
    // Dealer 16 draws a 2 (the filler) → 18: seat 0 (17) loses, seat 1 (18) pushes.
    expect(array_map(fn (array $hands) => $hands[0]['result'], $state['hands']))->toBe(['lose', 'push']);
});

it('starts the next round after the pause and ends after the last one', function () {
    $engine = new BlackjackEngine;
    $state = blackjack(1, ['rounds' => 2]);
    $state = bj(stackShoe($state, ['AS', '9H', 'KD', '7C']), 'bet', 100);   // +150
    $state = $engine->resume($state, new SecureRandom)->state;

    expect($state['round'])->toBe(2)->and($state['phase'])->toBe('betting')->and($state['hands'])->toBe([[]]);

    $state = bj(stackShoe($state, ['TS', '9H', '6D', '8C', 'KH']), 'bet', 100);
    $state = bj($state, 'hit');     // bust, -100

    expect($engine->outcome($state))->toEqual(new Outcome([0], 'rounds_played', [50]))
        ->and($engine->pauseMs($state))->toBeNull();
});

it('gives the game to the house when nobody finishes ahead', function () {
    $state = bj(stackShoe(blackjack(1, ['rounds' => 1]), ['TS', '9H', '6D', '8C', 'KH']), 'bet', 100);
    $state = bj($state, 'hit');

    expect((new BlackjackEngine)->outcome($state))->toEqual(new Outcome([Outcome::HOUSE], 'rounds_played', [-100]));
});

it('takes a player with no chips left off the table', function () {
    $state = bj(stackShoe(blackjack(2, ['stacks' => [100, 1000]]), []), 'bet', 100);
    $state = bj(stackShoe($state, ['TS', 'TD', '9H', '6S', '9D', '8C', 'KH']), 'bet', 10);
    $state = bj($state, 'hit');     // seat 0 busts with everything
    $state = bj($state, 'stand');

    expect($state['out'])->toBe([true, false])
        ->and((new BlackjackEngine)->chipsOf($state, 0))->toBe(0);

    $state = (new BlackjackEngine)->resume($state, new SecureRandom)->state;
    expect($state['to_act'])->toBe(1);
});

it('counts chips in play until a hand is settled', function () {
    $engine = new BlackjackEngine;
    $state = bj(stackShoe(blackjack(), ['8S', '9H', '8D', '7C', '3H', '2D']), 'bet', 100);
    $state = bj($state, 'split');

    expect($state['stacks'][0])->toBe(800)->and($engine->chipsOf($state, 0))->toBe(1000);
});

it('forfeits the bets in play of a player who leaves, and returns a bet placed before the deal', function () {
    $engine = new BlackjackEngine;
    $state = bj(blackjack(3), 'bet', 50);
    $left = $engine->depart($state, 0, new SecureRandom)->state;
    expect($left['stacks'][0])->toBe(1000)->and($left['to_act'])->toBe(1);

    $state = bj(stackShoe($state, ['TS', 'TD', 'TH', '9H', '7S', '8D', '7C', '6C']), 'bet', 20);
    $state = bj($state, 'bet', 30);
    expect($state['to_act'])->toBe(0);

    $state = $engine->depart($state, 0, new SecureRandom)->state;
    expect($engine->chipsOf($state, 0))->toBe(950)
        ->and($state['to_act'])->toBe(1);

    $state = bj(bj($state, 'stand'), 'stand');
    expect($state['last_round']['net'][0])->toBe(-50);
});

it('ends the game when the last seat leaves', function () {
    $state = (new BlackjackEngine)->depart(blackjack(), 0, new SecureRandom)->state;

    expect((new BlackjackEngine)->outcome($state)?->reason)->toBe('table_empty');
});

it('bets the minimum or stands when a player runs out of time', function () {
    $engine = new BlackjackEngine;
    $state = blackjack();
    expect($engine->timeoutAction($state, 0)->toArray())->toBe(['type' => 'bet', 'amount' => 10]);

    $state = bj(stackShoe($state, ['TS', '9H', '6D', '8C']), 'bet', 100);
    expect($engine->timeoutAction($state, 0)->type)->toBe('stand');
});

it('counts aces as one or eleven', function () {
    expect(BlackjackCards::total(['AS', 'AD', '9C']))->toBe([21, true])
        ->and(BlackjackCards::total(['AS', 'KD', '9C']))->toBe([20, false])
        ->and(BlackjackCards::isBlackjack(['AS', 'QD']))->toBeTrue()
        ->and(BlackjackCards::isBlackjack(['AS', '5D', '5C']))->toBeFalse();
});

it('plays basic strategy', function () {
    $bot = new BlackjackBot;

    expect($bot->basic(['8S', '8D'], 10, true, true))->toBe('split')
        ->and($bot->basic(['TS', 'TD'], 6, true, true))->toBe('stand')
        ->and($bot->basic(['6S', '5D'], 6, true, false))->toBe('double')
        ->and($bot->basic(['6S', '5D'], 6, false, false))->toBe('hit')
        ->and($bot->basic(['TS', '6D'], 10, false, false))->toBe('hit')
        ->and($bot->basic(['TS', '6D'], 5, false, false))->toBe('stand')
        ->and($bot->basic(['AS', '7D'], 9, true, false))->toBe('hit')
        ->and($bot->basic(['AS', '7D'], 4, true, false))->toBe('double')
        ->and($bot->basic(['AS', '7D'], 4, false, false))->toBe('stand');
});

it('lets bots of every difficulty play whole games', function (BotDifficulty $difficulty) {
    $engine = new BlackjackEngine;
    $bot = new BlackjackBot;
    $random = new SecureRandom;
    $state = $engine->initialState(new GameSetup(3, ['starting_chips' => 500, 'table_min_bet' => 10, 'rounds' => 10]), $random);

    for ($step = 0; $step < 500 && $engine->outcome($state) === null; $step++) {
        if ($engine->pauseMs($state) !== null) {
            $state = $engine->resume($state, $random)->state;

            continue;
        }
        $seat = $engine->seatsToAct($state)[0];
        $action = $bot->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), $difficulty, $random);
        expect($engine->isLegal($state, $seat, $action))->toBeTrue();
        $state = $engine->apply($state, $seat, $action, $random)->state;
    }

    expect($engine->outcome($state))->not->toBeNull();
})->with([BotDifficulty::Easy, BotDifficulty::Medium, BotDifficulty::Hard]);
