<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Games\Blot\Bot\BlotBot;
use App\Games\Blot\Bot\QuanshAdvisor;
use App\Games\Blot\Bot\QuanshSkill;
use App\Games\Blot\Engine\BlotEngine;
use Tests\Fixtures\Blot\QuanshLab;
use Tests\Fixtures\Blot\SeededRandom;

/**
 * Seat 0 opens the bazar with $bid (['value' => …, 'suit' => …] or 'kaput' => suit); seat 1 (the bot) holds
 * $mine. The other cards are dealt in order. Returns seat 1's view: it never holds anyone else's cards.
 *
 * @param  list<string>  $mine
 */
function quanshView(array $mine, array $bid): array
{
    $engine = new BlotEngine;
    $random = new SeededRandom(7);
    $state = $engine->resume($engine->initialState(new GameSetup(4), $random), $random)->state;
    $rest = [];
    foreach (BlotEngine::BID_SUITS as $suit) {
        if ($suit !== 'N') {
            foreach (str_split('789TJQKA') as $rank) {
                if (! in_array($rank.$suit, $mine, true)) {
                    $rest[] = $rank.$suit;
                }
            }
        }
    }
    $state['hands'] = [array_slice($rest, 0, 8), $mine, array_slice($rest, 8, 8), array_slice($rest, 16, 8)];
    [$state['dealer'], $state['to_act'], $state['leader']] = [3, 0, 0];
    $action = isset($bid['kaput']) ? new Action('kaput', ['suit' => $bid['kaput']]) : new Action('bid', $bid);
    $state = $engine->apply($state, 0, $action, $random)->state;

    return $engine->viewFor($state, 1);
}

/** How many of $tries different random streams lead $skill to Quansh. */
function quanshes(array $view, QuanshSkill $skill, int $tries = 6): int
{
    $calls = 0;
    foreach (range(1, $tries) as $seed) {
        $calls += (new QuanshAdvisor)->shouldQuansh($view, $skill, new SeededRandom($seed * 101)) ? 1 : 0;
    }

    return $calls;
}

it('maps the platform difficulties onto Beginner, Advanced and Expert', function () {
    expect(QuanshSkill::of(BotDifficulty::Easy))->toBe(QuanshSkill::Beginner)
        ->and(QuanshSkill::of(BotDifficulty::Medium))->toBe(QuanshSkill::Advanced)
        ->and(QuanshSkill::of(BotDifficulty::Hard))->toBe(QuanshSkill::Expert);
});

it('judges from the seat view only: no other hand is in it', function () {
    $view = quanshView(['JH', '9H', 'AH', 'TH', 'AS', 'AD', 'AC', 'TS'], ['value' => 14, 'suit' => 'H']);

    expect($view)->not->toHaveKey('hands')
        ->and((new QuanshAdvisor)->judge($view, QuanshSkill::Expert, new SeededRandom(3)))->toHaveKeys(['quansh', 'fail', 'gain']);
});

it('never misses a Quansh against a bid in the trump it holds the top of', function () {
    // The bot holds J 9 A 10 of hearts and three side aces: a 14 in hearts can't be made.
    $view = quanshView(['JH', '9H', 'AH', 'TH', 'AS', 'AD', 'AC', 'TS'], ['value' => 14, 'suit' => 'H']);
    $judged = (new QuanshAdvisor)->judge($view, QuanshSkill::Expert, new SeededRandom(5));

    expect($judged['fail'])->toBeGreaterThan(0.9)
        ->and($judged['gain'])->toBeGreaterThan(5)
        ->and(quanshes($view, QuanshSkill::Expert))->toBe(6)
        ->and(quanshes($view, QuanshSkill::Advanced))->toBe(6)
        ->and(quanshes($view, QuanshSkill::Intermediate))->toBe(6)
        // A beginner sees it too, but not every time.
        ->and(quanshes($view, QuanshSkill::Beginner, 12))->toBeGreaterThan(3)->toBeLessThan(12);
});

it('never says Quansh on a hand with nothing against the contract', function () {
    $view = quanshView(['7S', '8S', '7D', '8D', '7C', '8C', '9C', '7H'], ['value' => 9, 'suit' => 'H']);

    foreach (QuanshSkill::cases() as $skill) {
        expect(quanshes($view, $skill))->toBe(0, $skill->value);
    }
});

it('punishes a kaput it can surely spoil, and leaves a likely one alone', function () {
    // The trump jack always takes a trick: the kaput fails.
    $spoiled = quanshView(['JS', '7H', '8H', '7D', '8D', '7C', '8C', '9C'], ['kaput' => 'S']);
    expect(quanshes($spoiled, QuanshSkill::Expert))->toBe(6)
        ->and(quanshes($spoiled, QuanshSkill::Advanced))->toBe(6)
        ->and(quanshes($spoiled, QuanshSkill::Intermediate))->toBe(6);

    // No trump and no ace: a contra'd kaput that is made loses the match, so no Quansh.
    $likely = quanshView(['7H', '8H', '7D', '8D', '9D', '7C', '8C', '9C'], ['kaput' => 'S']);
    expect(quanshes($likely, QuanshSkill::Expert))->toBe(0)
        ->and(quanshes($likely, QuanshSkill::Advanced))->toBe(0);
});

it('quanshes through the bot itself in the bazar', function () {
    $view = quanshView(['JH', '9H', 'AH', 'TH', 'AS', 'AD', 'AC', 'TS'], ['value' => 14, 'suit' => 'H']);
    $engine = new BlotEngine;
    $legal = [new Action('bid', ['max' => BlotEngine::MAX_BID, 'min' => 15]), new Action('pass'), new Action('contra')];

    expect((new BlotBot)->chooseAction($view, $legal, BotDifficulty::Hard, new SeededRandom(9))->type)->toBe('contra');
});

it('gets better with skill over many random deals, and Expert seldom misses or errs', function () {
    // 40 deals; the opener overbids in about 4 of 10. See QuanshLab for what is counted.
    $stats = QuanshLab::run(40, 2026, QuanshSkill::cases());
    $expert = $stats['expert'];
    $beginner = $stats['beginner'];

    expect($expert['obvious'])->toBeGreaterThan(0)
        ->and($expert['missed'])->toBeLessThanOrEqual(intdiv($expert['obvious'], 4))
        ->and($expert['wrong'])->toBeLessThanOrEqual(intdiv($expert['calls'], 3))
        ->and($expert['gain'])->toBeGreaterThan(0)
        ->and($beginner['missed'])->toBeGreaterThanOrEqual($expert['missed'])
        ->and($expert['gain'])->toBeGreaterThanOrEqual($stats['advanced']['gain'] - 10);
});
