<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\SecureRandom;
use App\Games\Nardi\Bot\NardiBot;
use App\Games\Nardi\Engine\LongNardiRules;
use App\Games\Nardi\Engine\NardiEngine;
use App\Games\Nardi\Engine\NardiRules;

/**
 * A Long nardi position. $seat0 / $seat1: [point => count]; checkers not placed are borne off.
 *
 * @param  array<int, int>  $seat0
 * @param  array<int, int>  $seat1
 * @param  list<int>  $dice
 */
function longNardi(array $seat0, array $seat1, array $dice, int $turn = 0, array $turns = [3, 3], int $headMoves = 0): array
{
    return [
        ...nardi($seat0, $seat1, $dice, $turn),
        'variant' => 'long',
        'head_moves' => $headMoves,
        'turns' => $turns,
    ];
}

it('starts every checker on the head: seat 0 on point 24, seat 1 on point 12', function () {
    $state = (new NardiEngine)->initialState(new GameSetup(2, ['variant' => 'long']), new SecureRandom);

    expect($state['variant'])->toBe('long')
        ->and($state['points'][23])->toBe(15)
        ->and($state['points'][11])->toBe(-15)
        ->and(array_sum(array_map('abs', $state['points'])))->toBe(30)
        ->and($state['turns'])->toBe([0, 0])
        ->and((new NardiEngine)->viewFor($state, 0)['pips'])->toBe([360, 360]);
});

it('keeps old states Short: a state without a variant plays backgammon rules', function () {
    $state = (new NardiEngine)->initialState(new GameSetup(2, []), new SecureRandom);

    expect($state)->not->toHaveKey('variant')
        ->and(NardiEngine::rules($state))->toBe(NardiRules::class);
});

it('moves both players the same way round the board, seat 1 wrapping from 1 to 24', function () {
    expect(LongNardiRules::point(0, 1))->toBe(24)->and(LongNardiRules::point(0, 24))->toBe(1)
        ->and(LongNardiRules::point(1, 1))->toBe(12)->and(LongNardiRules::point(1, 12))->toBe(1)
        ->and(LongNardiRules::point(1, 13))->toBe(24)->and(LongNardiRules::point(1, 24))->toBe(13);

    // Seat 1's checker on point 3 with a 5 goes to 22 (3, 2, 1, 24, 23, 22).
    $state = longNardi([24 => 15], [3 => 1, 12 => 14], [5, 1], turn: 1, headMoves: 1);
    expect(steps($state))->toContain('3>22/5');
});

it('closes a point with any opponent checker on it, even one: nothing is hit', function () {
    // Seat 0 from 20 with a 3 would land on 17, where one of seat 1's checkers is.
    $state = longNardi([20 => 1, 24 => 14], [17 => 1, 12 => 14], [3, 5], headMoves: 1);

    expect(steps($state))->not->toContain('20>17/3')->toContain('20>15/5');
    expect(LongNardiRules::applyStep($state, 0, ['from' => 20, 'to' => 15, 'die' => 5])[1])->toBeFalse();
});

it('lets only one checker leave the head in a turn', function () {
    $state = longNardi([24 => 15], [12 => 15], [5, 2]);
    expect(steps($state))->toBe(['24>19/5', '24>22/2']);

    [$after] = LongNardiRules::applyStep($state, 0, ['from' => 24, 'to' => 19, 'die' => 5]);
    $after['dice'] = [2];
    expect(steps($after))->toBe(['19>17/2']);
});

it('lets two leave the head on a first turn with 6-6, 4-4 or 3-3, and only then', function () {
    $first66 = longNardi([24 => 15], [12 => 15], [6, 6, 6, 6], turns: [0, 0]);
    $first66['rolled'] = [6, 6];
    expect(LongNardiRules::headLimit($first66, 0))->toBe(2);

    $first55 = [...$first66, 'rolled' => [5, 5], 'dice' => [5, 5, 5, 5]];
    expect(LongNardiRules::headLimit($first55, 0))->toBe(1);

    $later66 = [...$first66, 'turns' => [1, 1]];
    expect(LongNardiRules::headLimit($later66, 0))->toBe(1);

    // First turn 4-4: two checkers off the head (24 → 20 twice), then onwards.
    $first44 = longNardi([24 => 15], [12 => 15], [4, 4, 4, 4], turns: [0, 0]);
    $first44['rolled'] = [4, 4];
    $engine = new NardiEngine;
    $state = $first44;
    $fromHead = 0;
    while ($state['turn'] === 0 && $state['dice'] !== []) {
        $step = collect($engine->legalSteps($state))->firstWhere('from', 24) ?? $engine->legalSteps($state)[0];
        $fromHead += $step['from'] === 24 ? 1 : 0;
        $state = $engine->apply($state, 0, new Action('move', $step), new SecureRandom)->state;
    }
    expect($fromHead)->toBe(2);
});

it('forbids a wall of six points in a row with no opponent checker ahead of it', function () {
    // Seat 1 is still on its head (point 12). Seat 0 holds 10..6, just ahead of it on seat 1's way;
    // 13 → 11 with a 2 would close 11..6, six in a row, with none of seat 1's checkers past them.
    $state = longNardi([6 => 1, 7 => 1, 8 => 1, 9 => 1, 10 => 1, 13 => 1, 24 => 9], [12 => 15], [2, 1], headMoves: 1);
    expect(LongNardiRules::walls(LongNardiRules::applyStep($state, 0, ['from' => 13, 'to' => 11, 'die' => 2])[0], 0))->toBeTrue()
        ->and(steps($state))->not->toContain('13>11/2');

    // With one of seat 1's checkers already past the wall (on 5), the same step is allowed.
    $passed = longNardi([6 => 1, 7 => 1, 8 => 1, 9 => 1, 10 => 1, 13 => 1, 24 => 9], [5 => 1, 12 => 14], [2, 1], headMoves: 1);
    expect(steps($passed))->toContain('13>11/2');
});

it('bears off only once all fifteen are home, the exact die or a higher one from the farthest', function () {
    $notYet = longNardi([3 => 14, 9 => 1], [14 => 15], [3, 6]);
    expect(collect(steps($notYet))->contains(fn ($s) => str_contains($s, 'off')))->toBeFalse();

    $home = longNardi([3 => 10, 2 => 5], [14 => 15], [3, 6]);
    expect(steps($home))->toContain('3>off/3')->toContain('3>off/6')->not->toContain('2>off/6');

    // Seat 1 bears off past 13.
    $seat1 = longNardi([24 => 15], [13 => 2, 15 => 13], [1, 3], turn: 1);
    expect(steps($seat1))->toContain('13>off/1')->toContain('15>off/3');
});

it('scores a mars when the loser bore off none', function () {
    $engine = new NardiEngine;
    // One checker left, dice 1 and 2: only one can be played, so the higher one is.
    $state = longNardi([1 => 1], [14 => 15], [1, 2]);
    expect(steps($state))->toBe(['1>off/2']);
    $state = $engine->apply($state, 0, new Action('move', ['from' => 1, 'to' => 'off', 'die' => 2]), new SecureRandom)->state;

    expect($state['winner'])->toBe(0)
        ->and($engine->outcome($state)->reason)->toBe('mars')
        ->and($engine->outcome($state)->scores)->toBe([0 => 2]);
});

it('plays whole games between bots to the end without breaking a rule', function () {
    $engine = new NardiEngine;
    $bot = new NardiBot($engine);

    foreach ([BotDifficulty::Easy, BotDifficulty::Medium] as $difficulty) {
        $random = new SecureRandom;
        $state = $engine->initialState(new GameSetup(2, ['variant' => 'long']), $random);

        for ($moves = 0; $moves < 2000 && $state['winner'] === null; $moves++) {
            $seat = $state['turn'];
            $legal = $engine->legalActions($state, $seat);
            expect($legal)->not->toBeEmpty();
            $action = $bot->chooseAction($engine->viewFor($state, $seat), $legal, $difficulty, $random);
            $state = $engine->apply($state, $seat, $action, $random)->state;

            // Nothing is ever hit, and every checker is accounted for.
            expect($state['bar'])->toBe([0, 0])
                ->and(checkers($state, 0))->toBe(15)->and(checkers($state, 1))->toBe(15);
        }

        expect($state['winner'])->not->toBeNull();
    }
});

it('refuses a step that is not among the legal ones', function () {
    $state = longNardi([24 => 15], [12 => 15], [5, 2]);

    expect(fn () => (new NardiEngine)->apply($state, 0, new Action('move', ['from' => 24, 'to' => 12, 'die' => 5]), new SecureRandom))
        ->toThrow(IllegalAction::class);
});
