<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\SecureRandom;
use App\Games\Domino\Bot\DominoBot;
use App\Games\Domino\Engine\DominoEngine;

function domino(int $seats = 2, array $options = []): array
{
    return (new DominoEngine)->initialState(new GameSetup($seats, $options), new SecureRandom);
}

/** A round in progress with these hands and line, $toAct to play. */
function dominoSpot(array $hands, array $line, int $toAct = 0, int $seats = 2): array
{
    $state = domino($seats);
    $state['hands'] = $hands;
    $state['line'] = array_map(fn (array $t) => ['tile' => $t, 'seat' => 0], $line);
    $state['lead_tile'] = null;
    $state['to_act'] = $toAct;

    return $state;
}

function dominoPlay(array $state, string $tile, string $end = 'left'): array
{
    return (new DominoEngine)->apply($state, $state['to_act'], new Action('play', ['tile' => $tile, 'end' => $end]), new SecureRandom)->state;
}

it('deals seven tiles each and lets the highest double lead it', function () {
    $state = domino(4);
    $all = array_merge(...$state['hands']);

    expect(array_map('count', $state['hands']))->toBe([7, 7, 7, 7])
        ->and(array_unique($all))->toHaveCount(28)
        ->and($state['to_act'])->toBe(array_search('6-6', $all, true) === false ? null : intdiv(array_search('6-6', $all, true), 7))
        ->and((new DominoEngine)->legalActions($state, $state['to_act'])[0]->toArray())->toBe(['type' => 'play', 'end' => 'left', 'tile' => '6-6']);
});

it('offers every tile that matches an end, at that end', function () {
    $state = dominoSpot([['3-5', '6-6', '2-4'], ['1-1']], [[3, 4], [4, 2]]);
    $moves = array_map(fn (Action $a) => $a->payload['tile'].'@'.$a->payload['end'], (new DominoEngine)->legalActions($state, 0));

    expect($moves)->toBe(['3-5@left', '2-4@right']);
});

it('turns a tile to match the end it joins', function () {
    $state = dominoSpot([['3-5', '6-2'], ['1-1', '5-5']], [[3, 4], [4, 2]]);
    $state = dominoPlay($state, '3-5', 'left');

    expect(array_column($state['line'], 'tile'))->toBe([[5, 3], [3, 4], [4, 2]]);
});

it('rejects a tile that doesn\'t match', function () {
    $state = dominoSpot([['1-6', '3-5'], ['1-1']], [[3, 4]]);

    expect(fn () => dominoPlay($state, '1-6', 'left'))->toThrow(IllegalAction::class);
});

it('passes for a player who can\'t play', function () {
    $state = dominoSpot([['3-5', '6-6'], ['1-1', '0-0']], [[3, 4]], seats: 2);
    $state = dominoPlay($state, '3-5', 'left');

    // Seat 1 has nothing for 5 or 4, nor has seat 0 (6-6): nobody can play, the round is blocked.
    expect($state['phase'])->toBe('over')
        ->and($state['last_round']['blocked'])->toBeTrue();
});

it('gives the turn to the next player who can play', function () {
    $state = dominoSpot([['3-5', '6-6'], ['1-1'], ['5-2', '0-0']], [[3, 4]], seats: 3);
    $state['out'] = [false, false, false];
    $state['scores'] = [0, 0, 0];
    $state = dominoPlay($state, '3-5', 'left');

    expect($state['to_act'])->toBe(2);
});

it('scores the others\' pips for the player who goes out', function () {
    $state = dominoSpot([['3-5'], ['1-1', '6-4'], ['2-2']], [[3, 4]], seats: 3);
    $state['out'] = [false, false, false];
    $state['scores'] = [0, 0, 0];
    $state = dominoPlay($state, '3-5', 'left');

    expect($state['last_round'])->toMatchArray(['winner' => 0, 'points' => 2 + 10 + 4, 'blocked' => false])
        ->and($state['scores'])->toBe([16, 0, 0])
        ->and((new DominoEngine)->pauseMs($state))->toBe(4000)
        ->and($state['leader'])->toBe(0);
});

it('gives a blocked round to the lowest pip count, and nothing on a tie', function () {
    $state = dominoSpot([['3-5', '1-1'], ['6-6', '0-2']], [[3, 4]]);
    $state = dominoPlay($state, '3-5', 'left');
    // Blocked: seat 0 has 1-1 (2), seat 1 has 6-6 + 0-2 (14).
    expect($state['last_round'])->toMatchArray(['winner' => 0, 'points' => 14, 'blocked' => true]);

    $tie = dominoSpot([['3-5', '1-1'], ['0-2', '6-6']], [[3, 4]]);
    $tie['hands'][1] = ['0-2'];
    $tie = dominoPlay($tie, '3-5', 'left');
    expect($tie['last_round'])->toMatchArray(['winner' => null, 'points' => 0])
        ->and($tie['scores'])->toBe([0, 0]);
});

it('deals the next round led by the last winner, and ends at the target', function () {
    $engine = new DominoEngine;
    $state = dominoSpot([['3-5'], ['6-6', '6-5']], [[3, 4]]);
    $state = dominoPlay($state, '3-5', 'left');
    $next = $engine->resume($state, new SecureRandom)->state;
    expect($next['round'])->toBe(2)->and($next['to_act'])->toBe(0)->and($next['lead_tile'])->toBeNull();

    $win = dominoSpot([['3-5'], ['6-6', '6-5']], [[3, 4]]);
    $win['scores'] = [80, 30];
    $win = dominoPlay($win, '3-5', 'left');
    expect($engine->outcome($win)?->winners)->toBe([0])
        ->and($engine->outcome($win)?->scores)->toBe([103, 30]);
});

it('hides the other hands', function () {
    $state = dominoSpot([['3-5', '1-1'], ['6-6', '0-2']], [[3, 4]]);
    $view = (new DominoEngine)->viewFor($state, 0);

    expect($view['hand'])->toBe(['3-5', '1-1'])
        ->and($view['hand_sizes'])->toBe([2, 2])
        ->and($view['ends'])->toBe([3, 4])
        ->and($view)->not->toHaveKey('hands')
        ->and(json_encode($view))->not->toContain('6-6');
});

it('ends the game when only one player is left', function () {
    $state = domino(3);
    $engine = new DominoEngine;
    $state = $engine->depart($state, 0, new SecureRandom)->state;
    expect($engine->outcome($state))->toBeNull()->and($state['hands'][0])->toBe([]);

    $state = $engine->depart($state, 1, new SecureRandom)->state;
    expect($engine->outcome($state)?->winners)->toBe([2]);
});

it('lets bots of every difficulty play whole games', function (BotDifficulty $difficulty, int $seats) {
    $engine = new DominoEngine;
    $bot = new DominoBot;
    $random = new SecureRandom;
    $state = $engine->initialState(new GameSetup($seats, ['target' => 100]), $random);

    for ($step = 0; $step < 3000 && $engine->outcome($state) === null; $step++) {
        if ($engine->pauseMs($state) !== null) {
            $state = $engine->resume($state, $random)->state;

            continue;
        }
        $seat = $engine->seatsToAct($state)[0];
        $action = $bot->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), $difficulty, $random);
        $state = $engine->apply($state, $seat, $action, $random)->state;
    }

    expect($engine->outcome($state))->not->toBeNull()
        ->and(max($state['scores']))->toBeGreaterThanOrEqual(100);
})->with([BotDifficulty::Easy, BotDifficulty::Medium, BotDifficulty::Hard])->with([2, 4]);
