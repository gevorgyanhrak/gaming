<?php

use App\Platform\Levels\LevelCurve;

function defaultCurve(): LevelCurve
{
    return new LevelCurve(base: 80, step: 20, after30Percent: 150, growthPercent: 10, maxLevel: 100);
}

it('needs a fixed step more for each level up to 30', function () {
    $curve = defaultCurve();

    expect($curve->toNext(1))->toBe(80)
        ->and($curve->toNext(2))->toBe(100)
        ->and($curve->toNext(29))->toBe(640)
        ->and($curve->totalFor(1))->toBe(0)
        ->and($curve->totalFor(2))->toBe(80)
        ->and($curve->totalFor(5))->toBe(80 + 100 + 120 + 140)
        ->and($curve->totalFor(30))->toBe(10_440);
});

it('gets noticeably harder after 30, and each level after needs more than the one before', function () {
    $curve = defaultCurve();

    expect($curve->toNext(30))->toBe(990)          // 660 × 150 %
        ->and($curve->toNext(31))->toBe(1089);     // × 1.1

    $previous = 0;
    foreach (range(1, 99) as $level) {
        expect($curve->toNext($level))->toBeGreaterThan($previous);
        $previous = $curve->toNext($level);
    }
});

it('finds the level for a total of XP, and stops at the top level', function () {
    $curve = defaultCurve();

    expect($curve->levelFor(0))->toBe(1)
        ->and($curve->levelFor(79))->toBe(1)
        ->and($curve->levelFor(80))->toBe(2)
        ->and($curve->levelFor(10_439))->toBe(29)
        ->and($curve->levelFor(10_440))->toBe(30)
        ->and($curve->levelFor(PHP_INT_MAX >> 8))->toBe(100);

    $small = new LevelCurve(base: 10, step: 0, after30Percent: 100, growthPercent: 1, maxLevel: 30);
    expect($small->levelFor(1_000_000))->toBe(30)->and($small->totalFor(31))->toBe($small->totalFor(30));
});
