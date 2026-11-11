<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Random\SecureRandom;
use App\Games\Pool\Bot\PoolBot;
use App\Games\Pool\Engine\PoolEngine;
use App\Games\Pool\Engine\PoolPhysics;

/** A table after the break with only these balls (others potted). */
function poolSpot(array $balls, array $groups = [null, null], int $toAct = 0): array
{
    $state = (new PoolEngine)->initialState(new GameSetup(2), new SecureRandom);
    $state['balls'] = array_replace(array_fill(0, 16, null), $balls);
    $state['groups'] = $groups;
    $state['broken'] = true;
    $state['to_act'] = $toAct;

    return $state;
}

function poolShoot(array $state, float $angle, float $power, ?array $at = null): array
{
    $payload = ['angle' => $angle, 'power' => $power, ...($at === null ? [] : ['x' => $at[0], 'y' => $at[1]])];

    return (new PoolEngine)->apply($state, $state['to_act'], new Action('shoot', $payload), new SecureRandom)->state;
}

it('rolls a ball as far as friction lets it, in a straight line', function () {
    $result = PoolPhysics::shoot([0 => [300.0, 635.0]], 0, 20);
    $distance = (0.2 * PoolPhysics::MAX_SPEED) ** 2 / (2 * PoolPhysics::DECEL);

    expect($result['balls'][0][0])->toEqualWithDelta(300 + $distance, 5)
        ->and($result['balls'][0][1])->toEqualWithDelta(635, 0.01)
        ->and($result['segments'][0][0])->toBe([0, 300.0, 635.0, 920.0, 0.0])
        ->and($result['duration_ms'])->toBeGreaterThan(2000);
});

it('bounces off a cushion', function () {
    $result = PoolPhysics::shoot([0 => [2000.0, 635.0]], 0, 30);

    expect($result['rails'])->toBe(1)
        ->and($result['balls'][0][0])->toBeLessThan(PoolPhysics::W - PoolPhysics::R)
        ->and($result['potted'])->toBe([]);
});

it('drops a ball into a corner and a side pocket', function () {
    $corner = PoolPhysics::shoot([0 => [400.0, 400.0]], 225, 30);
    expect($corner['potted'])->toBe([0]);

    $side = PoolPhysics::shoot([0 => [1270.0, 500.0], 1 => [1270.0, 150.0]], -90, 35);
    expect($side['first_contact'])->toBe(1)->and($side['potted'])->toContain(1);
});

it('passes a straight hit on to the object ball', function () {
    $result = PoolPhysics::shoot([0 => [500.0, 635.0], 1 => [900.0, 635.0]], 0, 30);

    expect($result['first_contact'])->toBe(1)
        ->and($result['balls'][1][0])->toBeGreaterThan(1500)
        ->and($result['balls'][0][0])->toBeLessThan(900);
});

it('plays the same shot the same way every time', function () {
    $rack = PoolEngine::rack(new SecureRandom);

    expect(PoolPhysics::shoot($rack, 0.3, 100))->toBe(PoolPhysics::shoot($rack, 0.3, 100));
});

it('racks fifteen balls with the 8 in the middle and nothing overlapping', function () {
    $balls = PoolEngine::rack(new SecureRandom);

    expect($balls)->toHaveCount(16)
        ->and($balls[0])->toBe(PoolEngine::HEAD)
        ->and($balls[8][0])->toEqualWithDelta(PoolEngine::FOOT[0] + 2 * (2 * PoolPhysics::R + 0.05) * sqrt(3) / 2, 0.1);
    foreach ($balls as $a => $p) {
        foreach ($balls as $b => $q) {
            if ($a < $b) {
                expect(hypot($p[0] - $q[0], $p[1] - $q[1]))->toBeGreaterThanOrEqual(2 * PoolPhysics::R);
            }
        }
    }
});

it('describes the shot and takes a cue ball position only when it is in hand', function () {
    $engine = new PoolEngine;
    $state = poolSpot([0 => [400.0, 400.0], 1 => [1500.0, 600.0], 8 => [2000.0, 600.0]]);

    expect($engine->legalActions($state, 0)[0]->toArray())->toBe(['type' => 'shoot', 'ball_in_hand' => false, 'max' => 100, 'min' => 1])
        ->and($engine->isLegal($state, 0, new Action('shoot', ['angle' => 10, 'power' => 50])))->toBeTrue()
        ->and($engine->isLegal($state, 0, new Action('shoot', ['angle' => 10, 'power' => 101])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('shoot', ['angle' => 10, 'power' => 50, 'x' => 100, 'y' => 100])))->toBeFalse()
        ->and($engine->isLegal($state, 1, new Action('shoot', ['angle' => 10, 'power' => 50])))->toBeFalse();

    $state['ball_in_hand'] = true;
    expect($engine->isLegal($state, 0, new Action('shoot', ['angle' => 10, 'power' => 50])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('shoot', ['angle' => 10, 'power' => 50, 'x' => 1500, 'y' => 610])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('shoot', ['angle' => 10, 'power' => 50, 'x' => 700, 'y' => 700])))->toBeTrue();
});

it('gives the potter their group and another shot', function () {
    $state = poolSpot([0 => [400.0, 400.0], 3 => [150.0, 150.0], 9 => [1500.0, 900.0], 8 => [2000.0, 600.0]]);
    $state = poolShoot($state, 225, 40);

    expect($state['groups'])->toBe(['solids', 'stripes'])
        ->and($state['last_shot']['potted'])->toBe([3])
        ->and($state['last_shot']['foul'])->toBeNull()
        ->and($state['next'])->toBe(0)
        ->and($state['phase'])->toBe('rolling');

    $engine = new PoolEngine;
    expect($engine->pauseMs($state))->toBe($state['last_shot']['duration_ms'] + 700)
        ->and($engine->seatsToAct($state))->toBe([]);
    $state = $engine->resume($state, new SecureRandom)->state;
    expect($engine->seatsToAct($state))->toBe([0]);
});

it('gives the opponent the cue ball in hand after a scratch', function () {
    $state = poolSpot([0 => [400.0, 400.0], 1 => [1500.0, 900.0], 8 => [2000.0, 600.0]]);
    $state = poolShoot($state, 225, 40);

    expect($state['last_shot']['foul'])->toBe('scratch')
        ->and($state['balls'][0])->toBeNull()
        ->and($state['ball_in_hand'])->toBeTrue()
        ->and($state['next'])->toBe(1);
});

it('calls a foul for hitting the other group first, or nothing', function () {
    $wrong = poolSpot([0 => [500.0, 635.0], 9 => [900.0, 635.0], 1 => [1500.0, 200.0], 8 => [2000.0, 1000.0]], ['solids', 'stripes']);
    expect(poolShoot($wrong, 0, 40)['last_shot']['foul'])->toBe('wrong_ball');

    $miss = poolSpot([0 => [500.0, 635.0], 1 => [1500.0, 200.0], 8 => [2000.0, 1000.0]], ['solids', 'stripes']);
    expect(poolShoot($miss, 180, 10)['last_shot']['foul'])->toBe('no_contact');
});

it('calls a foul when nothing reaches a cushion after the contact', function () {
    $state = poolSpot([0 => [1000.0, 635.0], 1 => [1080.0, 635.0], 8 => [2000.0, 1000.0]], ['solids', 'stripes']);
    $state = poolShoot($state, 0, 4);

    expect($state['last_shot']['foul'])->toBe('no_rail')->and($state['ball_in_hand'])->toBeTrue();
});

it('loses on an 8 potted before the group is cleared, and wins after', function () {
    $early = poolSpot([0 => [400.0, 400.0], 8 => [150.0, 150.0], 1 => [1500.0, 900.0]], ['solids', 'stripes']);
    $early = poolShoot($early, 225, 40);
    expect((new PoolEngine)->outcome($early)?->winners)->toBe([1]);

    $won = poolSpot([0 => [400.0, 400.0], 8 => [150.0, 150.0], 9 => [1500.0, 900.0]], ['solids', 'stripes']);
    $won = poolShoot($won, 225, 40);
    expect((new PoolEngine)->outcome($won)?->winners)->toBe([0])
        ->and((new PoolEngine)->outcome($won)?->reason)->toBe('eight_potted');
});

it('takes a soft shot at a target for a player whose time runs out', function () {
    $engine = new PoolEngine;
    $state = poolSpot([0 => [400.0, 635.0], 1 => [1500.0, 635.0], 8 => [2000.0, 1000.0]], ['solids', 'stripes']);
    $action = $engine->timeoutAction($state, 0);

    expect($engine->isLegal($state, 0, $action))->toBeTrue()
        ->and($action->payload['angle'])->toEqualWithDelta(0, 0.01);

    $state['ball_in_hand'] = true;
    $state['balls'][0] = null;
    expect($engine->isLegal($state, 0, $engine->timeoutAction($state, 0)))->toBeTrue();
});

it('lets bots play a whole game', function (BotDifficulty $difficulty) {
    $engine = new PoolEngine;
    $bot = new PoolBot;
    $random = new SecureRandom;
    $state = $engine->initialState(new GameSetup(2), $random);

    for ($shot = 0; $shot < 400 && $engine->outcome($state) === null; $shot++) {
        if ($engine->pauseMs($state) !== null) {
            $state = $engine->resume($state, $random)->state;
        }
        $seat = $engine->seatsToAct($state)[0];
        $action = $bot->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), $difficulty, $random);
        expect($engine->isLegal($state, $seat, $action))->toBeTrue();
        $state = $engine->apply($state, $seat, $action, $random)->state;
    }

    expect($engine->outcome($state))->not->toBeNull();
})->with([BotDifficulty::Easy, BotDifficulty::Hard]);
