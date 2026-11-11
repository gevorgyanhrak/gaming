<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\SecureRandom;
use App\Games\Rummy\Bot\RummyBot;
use App\Games\Rummy\Engine\RummyEngine;

function rummy(int $seats = 2): array
{
    return (new RummyEngine)->initialState(new GameSetup($seats), new SecureRandom);
}

/** Seat 0 to play with these hands, the stock and discard given. */
function rummySpot(array $hands, array $discard = ['9D'], array $stock = ['2C', '3C', '4C'], string $phase = 'draw'): array
{
    $state = rummy(count($hands));
    $state['hands'] = $hands;
    $state['discard'] = $discard;
    $state['stock'] = $stock;
    $state['melds'] = [];
    $state['to_act'] = 0;
    $state['phase'] = $phase;
    $state['taken'] = null;
    $state['restocked'] = false;

    return $state;
}

function rummyAct(array $state, string $type, array $payload = []): array
{
    return (new RummyEngine)->apply($state, $state['to_act'], new Action($type, $payload), new SecureRandom)->state;
}

function rummyTypes(array $state): array
{
    return array_map(fn (Action $a) => $a->type.':'.implode('|', $a->payload), (new RummyEngine)->legalActions($state, $state['to_act']));
}

it('deals by table size and turns up a discard', function (int $seats, int $cards, int $decks) {
    $state = rummy($seats);

    expect(array_map('count', $state['hands']))->toBe(array_fill(0, $seats, $cards))
        ->and(count($state['stock']) + count($state['discard']) + $seats * $cards)->toBe(52 * $decks)
        ->and($state['discard'])->toHaveCount(1)
        ->and($state['phase'])->toBe('draw')
        ->and($state['to_act'])->toBe(($state['dealer'] + 1) % $seats);
})->with([[2, 10, 1], [4, 7, 1], [6, 6, 2]]);

it('recognises sets and runs, with aces low', function () {
    expect(RummyEngine::meldKind(['7S', '7H', '7D']))->toBe('set')
        ->and(RummyEngine::meldKind(['7S', '7H', '7D', '7C']))->toBe('set')
        ->and(RummyEngine::meldKind(['7S', '7S', '7D']))->toBeNull()
        ->and(RummyEngine::meldKind(['AS', '2S', '3S']))->toBe('run')
        ->and(RummyEngine::meldKind(['QS', 'KS', 'AS']))->toBeNull()
        ->and(RummyEngine::meldKind(['4H', '5H', '7H']))->toBeNull()
        ->and(RummyEngine::meldKind(['4H', '5H']))->toBeNull();
});

it('draws from the stock or takes the discard', function () {
    $state = rummySpot([['AS', '5D'], ['KC']]);
    expect(rummyTypes($state))->toBe(['draw:stock', 'draw:discard']);

    $taken = rummyAct($state, 'draw', ['from' => 'discard']);
    expect($taken['hands'][0])->toBe(['AS', '5D', '9D'])
        ->and($taken['discard'])->toBe([])
        ->and($taken['taken'])->toBe('9D')
        ->and($taken['phase'])->toBe('play');

    $drawn = rummyAct($state, 'draw', ['from' => 'stock']);
    expect($drawn['hands'][0])->toBe(['AS', '5D', '2C'])->and($drawn['stock'])->toBe(['3C', '4C']);
});

it('offers every meld and won\'t discard the card just taken', function () {
    $state = rummySpot([['7S', '7H', '7D', '8S', '9S', 'KD'], ['KC']], discard: ['6S']);
    $state = rummyAct($state, 'draw', ['from' => 'discard']);
    $types = rummyTypes($state);

    expect($types)->toContain('meld:7S,7H,7D', 'meld:6S,7S,8S,9S', 'meld:7S,8S,9S', 'meld:6S,7S,8S')
        ->and($types)->toContain('discard:KD')
        ->and($types)->not->toContain('discard:6S');
});

it('lays off a card on a meld on the table', function () {
    $state = rummySpot([['5H', 'KD', 'QC'], ['KC']], phase: 'play');
    $state['melds'] = [['kind' => 'run', 'cards' => ['2H', '3H', '4H'], 'seat' => 1]];

    expect(rummyTypes($state))->toContain('layoff:5H|0');
    $state = rummyAct($state, 'layoff', ['card' => '5H', 'meld' => 0]);
    expect($state['melds'][0]['cards'])->toBe(['2H', '3H', '4H', '5H'])
        ->and($state['hands'][0])->toBe(['KD', 'QC']);
});

it('passes the turn on a discard', function () {
    $state = rummySpot([['5H', 'KD'], ['KC']], phase: 'play');
    $state = rummyAct($state, 'discard', ['card' => 'KD']);

    expect($state['to_act'])->toBe(1)->and($state['phase'])->toBe('draw')->and($state['discard'])->toBe(['9D', 'KD']);
});

it('rejects a meld that isn\'t one, or a move out of turn', function () {
    $state = rummySpot([['7S', '7H', '8D'], ['KC']], phase: 'play');

    expect(fn () => rummyAct($state, 'meld', ['cards' => '7S,7H,8D']))->toThrow(IllegalAction::class)
        ->and(fn () => (new RummyEngine)->apply($state, 1, new Action('discard', ['card' => 'KC']), new SecureRandom))->toThrow(IllegalAction::class);
});

it('goes out and scores the cards left in the other hands', function () {
    $state = rummySpot([['7S', '7H', '7D', 'KD'], ['KC', 'AH', '5S'], ['2D']], phase: 'play');
    $state['out'] = [false, false, false];
    $state['scores'] = [0, 0, 0];
    $state = rummyAct($state, 'meld', ['cards' => '7S,7H,7D']);
    $state = rummyAct($state, 'discard', ['card' => 'KD']);

    expect($state['last_round'])->toMatchArray(['winner' => 0, 'points' => 10 + 1 + 5 + 2])
        ->and($state['scores'])->toBe([18, 0, 0])
        ->and($state['phase'])->toBe('over')
        ->and((new RummyEngine)->pauseMs($state))->toBe(5000);

    $next = (new RummyEngine)->resume($state, new SecureRandom)->state;
    expect($next['round'])->toBe(2)->and($next['melds'])->toBe([]);
});

it('remakes the stock from the discard pile', function () {
    $state = rummySpot([['5H'], ['KC']], discard: ['2D', '3D', '4D'], stock: []);
    $state = rummyAct($state, 'draw', ['from' => 'stock']);

    expect($state['discard'])->toBe(['4D'])
        ->and(count($state['stock']))->toBe(1)
        ->and($state['hands'][0])->toHaveCount(2);
});

it('ends the round without a winner when the remade stock runs out too', function () {
    $state = rummySpot([['5H', 'KD'], ['KC', 'QD']], discard: ['2D', '3D'], stock: []);
    $state['restocked'] = true;
    expect(rummyTypes($state))->toBe(['draw:discard']);

    $state = rummyAct(rummyAct($state, 'draw', ['from' => 'discard']), 'discard', ['card' => 'KD']);
    expect($state['phase'])->toBe('over')
        ->and($state['last_round'])->toMatchArray(['winner' => null, 'points' => 0])
        ->and($state['scores'])->toBe([0, 0]);
});

it('wins at the target', function () {
    $state = rummySpot([['7S', '7H', '7D'], ['KC', 'KD']], phase: 'play');
    $state['scores'] = [90, 40];
    $state = rummyAct($state, 'meld', ['cards' => '7S,7H,7D']);

    expect((new RummyEngine)->outcome($state)?->winners)->toBe([0])
        ->and($state['scores'])->toBe([110, 40]);
});

it('shows only your own cards and the top of the discard pile', function () {
    $state = rummySpot([['7S', '7H'], ['KC', 'QD']], discard: ['2D', '9D']);
    $view = (new RummyEngine)->viewFor($state, 0);

    expect($view['hand'])->toBe(['7S', '7H'])
        ->and($view['hand_sizes'])->toBe([2, 2])
        ->and($view['discard_top'])->toBe('9D')
        ->and($view['stock_size'])->toBe(3)
        ->and($view)->not->toHaveKeys(['hands', 'stock', 'discard'])
        ->and(json_encode($view))->not->toContain('QD');
});

it('draws and discards for a player who runs out of time', function () {
    $engine = new RummyEngine;
    $state = rummySpot([['5H', 'KD'], ['KC']]);
    expect($engine->timeoutAction($state, 0)?->toArray())->toBe(['type' => 'draw', 'from' => 'stock']);

    $state['phase'] = 'play';
    expect($engine->timeoutAction($state, 0)?->toArray())->toBe(['type' => 'discard', 'card' => 'KD']);
});

it('ends the game when only one player is left', function () {
    $engine = new RummyEngine;
    $state = rummy(3);
    $state = $engine->depart($state, 0, new SecureRandom)->state;
    $state = $engine->depart($state, 1, new SecureRandom)->state;

    expect($engine->outcome($state)?->winners)->toBe([2]);
});

it('lets bots of every difficulty play whole games', function (BotDifficulty $difficulty, int $seats) {
    $engine = new RummyEngine;
    $bot = new RummyBot;
    $random = new SecureRandom;
    $state = $engine->initialState(new GameSetup($seats), $random);

    for ($step = 0; $step < 20000 && $engine->outcome($state) === null; $step++) {
        if ($engine->pauseMs($state) !== null) {
            $state = $engine->resume($state, $random)->state;

            continue;
        }
        $seat = $engine->seatsToAct($state)[0];
        $action = $bot->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), $difficulty, $random);
        $state = $engine->apply($state, $seat, $action, $random)->state;
    }

    expect($engine->outcome($state))->not->toBeNull();
})->with([BotDifficulty::Easy, BotDifficulty::Medium, BotDifficulty::Hard])->with([2, 4]);
