<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\ReplayRandom;
use App\Gameplay\Random\SecureRandom;
use App\Games\Ludo\Bot\LudoBot;
use App\Games\Ludo\Engine\LudoEngine;

function ludo(int $seats = 2): array
{
    return (new LudoEngine)->initialState(new GameSetup($seats), new SecureRandom);
}

function ludoRoll(array $state, int $dice): array
{
    return (new LudoEngine)->apply($state, $state['to_act'], new Action('roll'), new ReplayRandom([$dice]))->state;
}

function ludoMove(array $state, int $token, int $to): array
{
    return (new LudoEngine)->apply($state, $state['to_act'], new Action('move', ['token' => $token, 'to' => $to]), new SecureRandom)->state;
}

function ludoMoves(array $state): array
{
    return array_map(fn (Action $a) => [$a->payload['token'], $a->payload['to']], (new LudoEngine)->legalActions($state, $state['to_act']));
}

it('gives two players opposite colours and starts with a roll', function () {
    $state = ludo(2);

    expect($state['colours'])->toBe([0, 2])
        ->and($state['tokens'])->toBe([[-1, -1, -1, -1], [-1, -1, -1, -1]])
        ->and(array_map(fn (Action $a) => $a->type, (new LudoEngine)->legalActions($state, 0)))->toBe(['roll'])
        ->and(ludo(4)['colours'])->toBe([0, 1, 2, 3]);
});

it('needs a 6 to leave the yard, and passes the turn when nothing can move', function () {
    $state = ludoRoll(ludo(), 4);

    expect($state['to_act'])->toBe(1)->and($state['phase'])->toBe('roll')->and($state['dice'])->toBe(4);

    $state = ludoRoll($state, 6);
    expect($state['phase'])->toBe('move')
        ->and(ludoMoves($state))->toBe([[0, 0], [1, 0], [2, 0], [3, 0]]);
});

it('rolls again after a 6', function () {
    $state = ludoMove(ludoRoll(ludo(), 6), 0, 0);

    expect($state['to_act'])->toBe(0)->and($state['phase'])->toBe('roll')->and($state['tokens'][0][0])->toBe(0);

    $state = ludoMove(ludoRoll($state, 3), 0, 3);
    expect($state['to_act'])->toBe(1)->and($state['tokens'][0][0])->toBe(3);
});

it('ends the turn on the third 6 in a row', function () {
    $state = ludo();
    $state['tokens'][0] = [10, -1, -1, -1];
    $state = ludoMove(ludoRoll($state, 6), 0, 16);
    $state = ludoMove(ludoRoll($state, 6), 0, 22);
    $state = ludoRoll($state, 6);

    expect($state['to_act'])->toBe(1)->and($state['tokens'][0][0])->toBe(22);
});

it('rejects a move that wasn\'t offered', function () {
    $state = ludoRoll(ludo(), 6);

    expect(fn () => ludoMove($state, 0, 5))->toThrow(IllegalAction::class)
        ->and(fn () => (new LudoEngine)->apply($state, 1, new Action('roll'), new SecureRandom))->toThrow(IllegalAction::class);
});

it('sends an opponent on an ordinary square back to the yard', function () {
    $state = ludo();
    // Red (colour 0) at track 10; yellow (colour 2) at its position 40 = square (40 + 26) % 52 = 14.
    $state['tokens'] = [[10, -1, -1, -1], [40, -1, -1, -1]];
    $state = ludoMove(ludoRoll($state, 4), 0, 14);

    expect($state['tokens'][1][0])->toBe(-1)
        ->and($state['last']['captured'])->toBe([['seat' => 1, 'token' => 0]]);
});

it('protects tokens on safe squares', function () {
    $state = ludo();
    // Square 13 (a start square) holds yellow at its position 39.
    $state['tokens'] = [[9, -1, -1, -1], [39, -1, -1, -1]];
    $state = ludoMove(ludoRoll($state, 4), 0, 13);

    expect($state['tokens'][1][0])->toBe(39);
});

it('takes the exact roll to get home, through the home column', function () {
    $state = ludo();
    $state['tokens'][0] = [53, 48, -1, -1];
    $state = ludoRoll($state, 5);

    // 53 + 5 overshoots home (56); 48 + 5 enters the home column.
    expect(ludoMoves($state))->toBe([[1, 53]]);
});

it('wins with all four tokens home', function () {
    $state = ludo();
    $state['tokens'][0] = [56, 56, 56, 53];
    $state = ludoMove(ludoRoll($state, 3), 3, 56);

    expect((new LudoEngine)->outcome($state)?->winners)->toBe([0])
        ->and((new LudoEngine)->outcome($state)?->scores)->toBe([4, 0]);
});

it('takes a leaver\'s tokens off the board and lets the last player win', function () {
    $engine = new LudoEngine;
    $state = ludo(3);
    $state = $engine->depart($state, 0, new SecureRandom)->state;
    expect($state['to_act'])->toBe(1)->and($engine->outcome($state))->toBeNull();

    $state = $engine->depart($state, 1, new SecureRandom)->state;
    expect($engine->outcome($state)?->winners)->toBe([2]);
});

it('maps positions to the shared track for every colour', function () {
    expect(LudoEngine::square(0, 0))->toBe(0)
        ->and(LudoEngine::square(1, 0))->toBe(13)
        ->and(LudoEngine::square(3, 50))->toBe(37)
        ->and(LudoEngine::square(0, 51))->toBeNull()
        ->and(LudoEngine::square(0, -1))->toBeNull();
});

it('lets bots of every difficulty play whole games', function (BotDifficulty $difficulty, int $seats) {
    $engine = new LudoEngine;
    $bot = new LudoBot;
    $random = new SecureRandom;
    $state = $engine->initialState(new GameSetup($seats), $random);

    for ($step = 0; $step < 20000 && $engine->outcome($state) === null; $step++) {
        $seat = $engine->seatsToAct($state)[0];
        $action = $bot->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), $difficulty, $random);
        $state = $engine->apply($state, $seat, $action, $random)->state;
    }

    expect($engine->outcome($state))->not->toBeNull();
})->with([BotDifficulty::Easy, BotDifficulty::Medium, BotDifficulty::Hard])->with([2, 4]);
