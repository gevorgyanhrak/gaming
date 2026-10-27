<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\SecureRandom;
use App\Games\Checkers\Bot\CheckersBot;
use App\Games\Checkers\Engine\CheckersEngine;
use App\Games\Checkers\Engine\CheckersRules;

/** Square index of an algebraic name: a1 is white's bottom-left corner. */
function dsq(string $name): int
{
    return (8 - (int) $name[1]) * 8 + (ord($name[0]) - ord('a'));
}

/**
 * @param  array<string, string>  $pieces  e.g. ['c3' => 'w', 'd4' => 'b', 'a1' => 'W']
 */
function draughts(array $pieces, int $turn = 0): array
{
    $board = array_fill(0, 64, null);
    foreach ($pieces as $name => $piece) {
        $board[dsq($name)] = $piece;
    }

    return ['board' => $board, 'turn' => $turn, 'capturing' => null, 'captured' => [], 'last' => null, 'quiet' => 0, 'seen' => [], 'result' => null];
}

function playDraughts(array $state, string $from, string $to): array
{
    return (new CheckersEngine)->apply($state, $state['turn'], new Action('move', ['from' => dsq($from), 'to' => dsq($to)]), new SecureRandom)->state;
}

/** @return list<string> e.g. ['c3-d4'] */
function draughtsSteps(array $state): array
{
    $names = fn (int $square) => chr(ord('a') + $square % 8).(8 - intdiv($square, 8));

    return array_map(fn (Action $a) => $names($a->payload['from']).'-'.$names($a->payload['to']), (new CheckersEngine)->legalActions($state, $state['turn']));
}

function draughtsPerft(array $board, int $seat, int $depth): int
{
    if ($depth === 0) {
        return 1;
    }

    return array_sum(array_map(fn (array $turn) => draughtsPerft($turn['board'], 1 - $seat, $depth - 1), CheckersRules::turns($board, $seat)));
}

it('sets up twelve men each with white to move', function () {
    $state = (new CheckersEngine)->initialState(new GameSetup(2), new SecureRandom);

    expect(CheckersRules::count($state['board'], 0))->toBe(12)
        ->and(CheckersRules::count($state['board'], 1))->toBe(12)
        ->and($state['board'][dsq('a1')])->toBe('w')
        ->and($state['board'][dsq('h8')])->toBe('b')
        ->and((new CheckersEngine)->seatsToAct($state))->toBe([0])
        ->and(draughtsSteps($state))->toHaveCount(7);
});

it('matches the known move counts from the starting position', function () {
    $board = CheckersRules::startingBoard();

    expect(array_map(fn (int $depth) => draughtsPerft($board, 0, $depth), [1, 2, 3, 4, 5]))->toBe([7, 49, 302, 1469, 7482]);
});

it('makes capturing mandatory', function () {
    expect(draughtsSteps(draughts(['c3' => 'w', 'g3' => 'w', 'd4' => 'b', 'a7' => 'b'])))->toBe(['c3-e5']);
});

it('lets men capture backwards', function () {
    expect(draughtsSteps(draughts(['d4' => 'w', 'c3' => 'b', 'a7' => 'b'])))->toBe(['d4-b2']);
});

it('continues a capture series and removes the jumped pieces only at its end', function () {
    $state = playDraughts(draughts(['c3' => 'w', 'd4' => 'b', 'f6' => 'b', 'a7' => 'b']), 'c3', 'e5');

    expect($state['turn'])->toBe(0)
        ->and($state['capturing'])->toBe(dsq('e5'))
        ->and($state['board'][dsq('d4')])->toBe('b')
        ->and(draughtsSteps($state))->toBe(['e5-g7']);

    $state = playDraughts($state, 'e5', 'g7');

    expect($state['turn'])->toBe(1)
        ->and($state['capturing'])->toBeNull()
        ->and($state['board'][dsq('d4')])->toBeNull()
        ->and($state['board'][dsq('f6')])->toBeNull()
        ->and(CheckersRules::count($state['board'], 1))->toBe(1);
});

it('never jumps a piece twice and treats jumped pieces as blocking', function () {
    $twice = draughts(['d4' => 'W', 'e5' => 'b'])['board'];
    $blocking = draughts(['a1' => 'W', 'c3' => 'b', 'e5' => 'b'])['board'];

    expect(CheckersRules::captureSteps($twice, [dsq('e5')], dsq('d4')))->toBe([])
        // From a1 the king can't fly over the already jumped c3 to reach the next piece.
        ->and(CheckersRules::captureSteps($blocking, [dsq('c3')], dsq('a1')))->toBe([]);
});

it('makes a king land where it can keep capturing', function () {
    expect(draughtsSteps(draughts(['a1' => 'W', 'c3' => 'b', 'f4' => 'b'])))->toBe(['a1-e5']);
});

it('lets a king land on any square beyond when no further capture follows', function () {
    expect(draughtsSteps(draughts(['a1' => 'W', 'c3' => 'b', 'a7' => 'b'])))->toBe(['a1-d4', 'a1-e5', 'a1-f6', 'a1-g7', 'a1-h8']);
});

it('crowns a man during a capture and lets it continue as a king', function () {
    $state = playDraughts(draughts(['b6' => 'w', 'c7' => 'b', 'f6' => 'b', 'a7' => 'b']), 'b6', 'd8');

    expect($state['board'][dsq('d8')])->toBe('W')
        ->and($state['capturing'])->toBe(dsq('d8'))
        ->and(draughtsSteps($state))->toBe(['d8-g5', 'd8-h4']);
});

it('wins when the opponent has no pieces left', function () {
    $state = playDraughts(draughts(['c3' => 'w', 'd4' => 'b'], 0), 'c3', 'e5');

    expect((new CheckersEngine)->outcome($state))->toEqual(new Outcome([0], 'no_pieces'))
        ->and((new CheckersEngine)->seatsToAct($state))->toBe([]);
});

it('wins when the opponent cannot move', function () {
    $state = playDraughts(draughts(['a1' => 'w', 'g5' => 'w', 'f4' => 'w', 'h6' => 'b']), 'a1', 'b2');

    expect($state['result'])->toBe(['winners' => [0], 'reason' => 'no_moves']);
});

it('draws on threefold repetition', function () {
    $engine = new CheckersEngine;
    $state = draughts(['a1' => 'W', 'h6' => 'B']);
    $state['seen'] = [CheckersRules::key($state['board'], 0) => 1];

    foreach (range(1, 2) as $round) {
        $state = playDraughts(playDraughts(playDraughts(playDraughts($state, 'a1', 'b2'), 'h6', 'g5'), 'b2', 'a1'), 'g5', 'h6');
    }

    expect($engine->outcome($state)?->reason)->toBe('repetition')
        ->and($engine->outcome($state)?->isDraw())->toBeTrue();
});

it('draws after fifteen moves each with kings only', function () {
    $state = [...draughts(['a1' => 'W', 'h8' => 'B']), 'quiet' => CheckersEngine::QUIET_LIMIT - 1];

    expect(playDraughts($state, 'a1', 'c3')['result'])->toBe(['winners' => [], 'reason' => 'kings_only']);
});

it('resets the quiet count on a man move', function () {
    $state = [...draughts(['a1' => 'W', 'c1' => 'w', 'h8' => 'B']), 'quiet' => 10];

    expect(playDraughts($state, 'c1', 'd2')['quiet'])->toBe(0)
        ->and(playDraughts($state, 'a1', 'b2')['quiet'])->toBe(11);
});

it('rejects illegal moves and moves out of turn', function () {
    $engine = new CheckersEngine;
    $state = $engine->initialState(new GameSetup(2), new SecureRandom);

    expect(fn () => playDraughts($state, 'c3', 'c4'))->toThrow(IllegalAction::class)
        ->and(fn () => $engine->apply($state, 1, new Action('move', ['from' => dsq('b6'), 'to' => dsq('a5')]), new SecureRandom))->toThrow(IllegalAction::class);
});

it('always finishes random games with the rules intact', function () {
    $engine = new CheckersEngine;
    $random = new SecureRandom;

    foreach (range(1, 25) as $game) {
        $state = $engine->initialState(new GameSetup(2), $random);
        $pieces = [12, 12];
        for ($i = 0; $i < 1000 && $engine->outcome($state) === null; $i++) {
            $legal = $engine->legalActions($state, $state['turn']);
            expect($legal)->not->toBeEmpty();
            $state = $engine->apply($state, $state['turn'], $legal[$random->int(0, count($legal) - 1)], $random)->state;

            foreach ([0, 1] as $seat) {
                $count = CheckersRules::count($state['board'], $seat);
                expect($count)->toBeLessThanOrEqual($pieces[$seat]);
                $pieces[$seat] = $count;
            }
            foreach ($state['board'] as $square => $piece) {
                expect($piece === null || CheckersRules::isDark($square))->toBeTrue();
            }
        }

        expect($engine->outcome($state))->not->toBeNull();
    }
});

it('has bots play legal moves at every level, including mid-capture', function (BotDifficulty $difficulty) {
    $engine = new CheckersEngine;
    $bot = new CheckersBot;
    $random = new SecureRandom;

    foreach ([$engine->initialState(new GameSetup(2), $random), playDraughts(draughts(['c3' => 'w', 'd4' => 'b', 'f6' => 'b', 'a7' => 'b']), 'c3', 'e5')] as $state) {
        $legal = $engine->legalActions($state, $state['turn']);
        $choice = $bot->chooseAction($engine->viewFor($state, $state['turn']), $legal, $difficulty, $random);

        expect(collect($legal)->contains(fn (Action $a) => $a->equals($choice)))->toBeTrue();
    }
})->with([BotDifficulty::Easy, BotDifficulty::Medium, BotDifficulty::Hard]);

it('has the medium bot beat random play', function () {
    $engine = new CheckersEngine;
    $bot = new CheckersBot;
    $random = new SecureRandom;
    $wins = 0;

    foreach (range(1, 6) as $game) {
        $botSeat = $game % 2;
        $state = $engine->initialState(new GameSetup(2), $random);
        for ($i = 0; $i < 600 && $engine->outcome($state) === null; $i++) {
            $legal = $engine->legalActions($state, $state['turn']);
            $level = $state['turn'] === $botSeat ? BotDifficulty::Medium : BotDifficulty::Easy;
            $state = $engine->apply($state, $state['turn'], $bot->chooseAction($engine->viewFor($state, $state['turn']), $legal, $level, $random), $random)->state;
        }
        $wins += $engine->outcome($state)?->winners === [$botSeat] ? 1 : 0;
    }

    expect($wins)->toBeGreaterThanOrEqual(5);
});

it('has the hard bot answer within a reasonable time', function () {
    $engine = new CheckersEngine;
    $random = new SecureRandom;
    $state = $engine->initialState(new GameSetup(2), $random);
    // A busy middlegame: play some random opening moves first.
    for ($i = 0; $i < 12 && $engine->outcome($state) === null; $i++) {
        $legal = $engine->legalActions($state, $state['turn']);
        $state = $engine->apply($state, $state['turn'], $legal[$random->int(0, count($legal) - 1)], $random)->state;
    }
    if ($engine->outcome($state) !== null) {
        $this->markTestSkipped('The random opening ended the game.');
    }

    $started = microtime(true);
    (new CheckersBot)->chooseAction($engine->viewFor($state, $state['turn']), $engine->legalActions($state, $state['turn']), BotDifficulty::Hard, $random);

    expect(microtime(true) - $started)->toBeLessThan(3.0);
});
