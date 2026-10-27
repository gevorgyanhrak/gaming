<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\ReplayRandom;
use App\Gameplay\Random\SecureRandom;
use App\Games\Nardi\Bot\NardiBot;
use App\Games\Nardi\Engine\NardiEngine;
use App\Games\Nardi\Engine\NardiRules;

/**
 * Build a position. $seat0 / $seat1: [point => count]; checkers not placed are borne off.
 *
 * @param  array<int, int>  $seat0
 * @param  array<int, int>  $seat1
 * @param  list<int>  $dice
 */
function nardi(array $seat0, array $seat1, array $dice, int $turn = 0, array $bar = [0, 0]): array
{
    $points = array_fill(0, 24, 0);
    foreach ($seat0 as $p => $n) {
        $points[$p - 1] = $n;
    }
    foreach ($seat1 as $p => $n) {
        $points[$p - 1] = -$n;
    }

    return [
        'points' => $points,
        'bar' => $bar,
        'off' => [15 - array_sum($seat0) - $bar[0], 15 - array_sum($seat1) - $bar[1]],
        'turn' => $turn,
        'dice' => $dice,
        'rolled' => array_slice($dice, 0, 2),
        'winner' => null,
    ];
}

/** @return list<string> e.g. ["13>8/5", "bar>20/5", "3>off/4"] */
function steps(array $state): array
{
    $steps = array_map(fn ($s) => "{$s['from']}>{$s['to']}/{$s['die']}", (new NardiEngine)->legalSteps($state));
    sort($steps);

    return $steps;
}

function checkers(array $state, int $seat): int
{
    $onBoard = 0;
    for ($p = 1; $p <= 24; $p++) {
        $onBoard += NardiRules::own($state, $seat, $p);
    }

    return $onBoard + $state['bar'][$seat] + $state['off'][$seat];
}

it('sets up the standard position and an opening roll won by the higher die', function () {
    $state = (new NardiEngine)->initialState(new GameSetup(2), new ReplayRandom([5, 3]));

    expect(checkers($state, 0))->toBe(15)->and(checkers($state, 1))->toBe(15)
        ->and(NardiRules::pips($state, 0))->toBe(167)->and(NardiRules::pips($state, 1))->toBe(167)
        ->and($state['turn'])->toBe(0)->and($state['dice'])->toBe([5, 3]);

    $tie = (new NardiEngine)->initialState(new GameSetup(2), new ReplayRandom([4, 4, 2, 6]));
    expect($tie['turn'])->toBe(1)->and($tie['dice'])->toBe([2, 6]);
});

it('does not land on points held by two or more opposing checkers', function () {
    $state = nardi([10 => 1, 6 => 14], [7 => 2, 4 => 13], [3, 1]);

    expect(steps($state))->not->toContain('10>7/3')->toContain('10>9/1');
});

it('hits single checkers and makes the hit side enter from the bar first', function () {
    $engine = new NardiEngine;
    $state = nardi([10 => 1, 6 => 14], [7 => 1, 19 => 14], [3, 2]);

    $after = $engine->apply($state, 0, new Action('move', ['from' => 10, 'to' => 7, 'die' => 3]), new ReplayRandom([]))->state;

    expect($after['bar'][1])->toBe(1)->and(NardiRules::own($after, 0, 7))->toBe(1)->and(checkers($after, 1))->toBe(15);

    // Seat 1 on the bar: only entering moves are allowed (seat 1 enters on point = die).
    $entering = nardi([6 => 14, 2 => 1], [19 => 14], [2, 5], turn: 1, bar: [0, 1]);
    expect(steps($entering))->toBe(['bar>2/2', 'bar>5/5']);

    $blocked = nardi([6 => 13, 5 => 2], [19 => 14], [5, 5, 5, 5], turn: 1, bar: [0, 1]);
    expect(steps($blocked))->toBe([]);
});

it('requires using both dice when possible', function () {
    // 20 -6-> 14 is blocked, so the only way to play both dice is 20 -1-> 19 -6-> 13.
    $state = nardi([20 => 1, 6 => 14], [14 => 2, 1 => 13], [6, 1]);

    expect(steps($state))->toBe(['20>19/1']);
});

it('requires the higher die when only one of them can be played', function () {
    // From 10: 5 then 3, or 3 then 5, both end on point 2, which is blocked.
    $state = nardi([10 => 1], [2 => 2, 24 => 13], [5, 3]);

    expect(steps($state))->toBe(['10>5/5']);
});

it('plays doubles four times', function () {
    $engine = new NardiEngine;
    $state = nardi([24 => 2, 6 => 13], [1 => 2, 19 => 13], [2, 2, 2, 2]);

    for ($i = 0; $i < 4; $i++) {
        expect($state['turn'])->toBe(0);
        $step = $engine->legalSteps($state)[0];
        $state = $engine->apply($state, 0, new Action('move', $step), new ReplayRandom([3, 4]))->state;
    }

    expect($state['turn'])->toBe(1)->and($state['dice'])->toBe([3, 4]);
});

it('bears off exactly, or with a higher die from the farthest checker only', function () {
    $state = nardi([5 => 1, 3 => 1], [19 => 15], [6, 3]);
    expect(steps($state))->toBe(['3>off/3', '5>2/3', '5>off/6']);

    $notAllHome = nardi([7 => 1, 3 => 1], [19 => 15], [4, 2]);
    expect(steps($notAllHome))->not->toContain('3>off/4');
});

it('passes the turn when the next player cannot move', function () {
    $engine = new NardiEngine;
    // Seat 0 holds points 1..6 (seat 1's entry points); seat 1 has a checker on the bar.
    $state = nardi([1 => 2, 2 => 2, 3 => 2, 4 => 2, 5 => 2, 6 => 2, 8 => 2, 10 => 1], [12 => 14], [1, 2], turn: 0, bar: [0, 1]);

    // Seat 0 plays 10->9 then 9->7; seat 1 rolls 3-5 (can't enter) and passes; seat 0 rolls 1-1.
    $state = $engine->apply($state, 0, new Action('move', ['from' => 10, 'to' => 9, 'die' => 1]), new ReplayRandom([]))->state;
    $transition = $engine->apply($state, 0, new Action('move', ['from' => 9, 'to' => 7, 'die' => 2]), new ReplayRandom([3, 5, 1, 1]));

    expect(collect($transition->events)->pluck('type')->all())->toBe(['moved', 'rolled', 'no_moves', 'rolled'])
        ->and($transition->state['turn'])->toBe(0)
        ->and($transition->state['dice'])->toBe([1, 1, 1, 1]);
});

it('wins by bearing off the last checker, double for a mars', function () {
    $engine = new NardiEngine;

    $win = $engine->apply(nardi([1 => 1], [19 => 3], [1, 2]), 0, new Action('move', ['from' => 1, 'to' => 'off', 'die' => 2]), new ReplayRandom([]));
    expect($engine->outcome($win->state)->reason)->toBe('bear_off')->and($engine->outcome($win->state)->scores)->toBe([0 => 1]);

    $mars = $engine->apply(nardi([1 => 1], [19 => 15], [2, 1]), 0, new Action('move', ['from' => 1, 'to' => 'off', 'die' => 2]), new ReplayRandom([]));
    expect($engine->outcome($mars->state)->reason)->toBe('mars')->and($engine->outcome($mars->state)->scores)->toBe([0 => 2]);
});

it('rejects anything that is not a legal step', function () {
    $state = (new NardiEngine)->initialState(new GameSetup(2), new ReplayRandom([6, 1]));

    expect(fn () => (new NardiEngine)->apply($state, 0, new Action('move', ['from' => 13, 'to' => 9, 'die' => 4]), new ReplayRandom([])))->toThrow(IllegalAction::class)
        ->and(fn () => (new NardiEngine)->apply($state, 1, new Action('move', ['from' => 1, 'to' => 7, 'die' => 6]), new ReplayRandom([])))->toThrow(IllegalAction::class);
});

it('plays random games to the end without losing or creating checkers', function () {
    $engine = new NardiEngine;
    $random = new SecureRandom;

    for ($game = 0; $game < 40; $game++) {
        $state = $engine->initialState(new GameSetup(2), $random);

        for ($moves = 0; $engine->outcome($state) === null; $moves++) {
            expect($moves)->toBeLessThan(2000);
            $seat = $engine->seatsToAct($state)[0];
            $legal = $engine->legalActions($state, $seat);
            expect($legal)->not->toBeEmpty();

            $state = $engine->apply($state, $seat, $legal[$random->int(0, count($legal) - 1)], $random)->state;

            expect(checkers($state, 0))->toBe(15)->and(checkers($state, 1))->toBe(15);
        }
    }
});

it('has bots that always choose legal steps', function (BotDifficulty $difficulty, int $games) {
    $engine = new NardiEngine;
    $bot = new NardiBot;
    $random = new SecureRandom;

    for ($game = 0; $game < $games; $game++) {
        $state = $engine->initialState(new GameSetup(2), $random);

        for ($moves = 0; $engine->outcome($state) === null && $moves < 400; $moves++) {
            $seat = $engine->seatsToAct($state)[0];
            $legal = $engine->legalActions($state, $seat);
            $choice = $bot->chooseAction($engine->viewFor($state, $seat), $legal, $difficulty, $random);

            expect(collect($legal)->contains(fn (Action $a) => $a->equals($choice)))->toBeTrue();
            $state = $engine->apply($state, $seat, $choice, $random)->state;
        }
    }
})->with([
    'easy' => [BotDifficulty::Easy, 5],
    'medium' => [BotDifficulty::Medium, 3],
    'hard' => [BotDifficulty::Hard, 1],
]);

it('lets the medium bot beat a random player most of the time', function () {
    $engine = new NardiEngine;
    $bot = new NardiBot;
    $random = new SecureRandom;
    $wins = 0;

    for ($game = 0; $game < 10; $game++) {
        $state = $engine->initialState(new GameSetup(2), $random);
        while ($engine->outcome($state) === null) {
            $seat = $engine->seatsToAct($state)[0];
            $legal = $engine->legalActions($state, $seat);
            $choice = $seat === 0
                ? $bot->chooseAction($state, $legal, BotDifficulty::Medium, $random)
                : $legal[$random->int(0, count($legal) - 1)];
            $state = $engine->apply($state, $seat, $choice, $random)->state;
        }
        $wins += $engine->outcome($state)->winners === [0] ? 1 : 0;
    }

    expect($wins)->toBeGreaterThanOrEqual(7);
});
