<?php

use App\Enums\BotPersonality;
use App\Gameplay\Bots\HumanPace;

it('takes longer over more choices, and is quick over a forced move', function () {
    $average = fn (int $choices) => array_sum(array_map(fn () => HumanPace::thinkingMs($choices, null), range(1, 400))) / 400;

    expect($average(1))->toBeLessThan(1400)
        ->and($average(32))->toBeGreaterThan($average(2))
        ->and($average(2))->toBeGreaterThan($average(1));
});

it('never thinks the same twice, and a cautious player thinks longer than a bold one', function () {
    $times = array_map(fn () => HumanPace::thinkingMs(8, BotPersonality::Steady), range(1, 50));
    expect(count(array_unique($times)))->toBeGreaterThan(20);

    $avg = fn (BotPersonality $p) => array_sum(array_map(fn () => HumanPace::thinkingMs(8, $p), range(1, 300))) / 300;
    expect($avg(BotPersonality::Cautious))->toBeGreaterThan($avg(BotPersonality::Bold));
});

it('lets AI players due together move one after another, inside the turn timer', function () {
    $first = array_map(fn () => HumanPace::thinkingMs(1, null, 0, baseMs: 1000), range(1, 50));
    $third = array_map(fn () => HumanPace::thinkingMs(1, null, 2, baseMs: 1000), range(1, 50));
    expect(min($third))->toBeGreaterThanOrEqual(2000)->and(max($first))->toBe(1000);

    expect(HumanPace::thinkingMs(64, BotPersonality::Cautious, 3, msLeft: 4000))->toBeLessThanOrEqual(1500);
});

it('plays below its best only now and then, more often for a tricky player', function () {
    expect(BotPersonality::Steady->lapsePercent())->toBeLessThan(BotPersonality::Tricky->lapsePercent())
        ->and(BotPersonality::Tricky->lapsePercent())->toBeLessThanOrEqual(15);
});
