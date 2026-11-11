<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\SecureRandom;
use App\Games\Blot\Bot\BlotBot;
use App\Games\Blot\Engine\BlotCards;
use App\Games\Blot\Engine\BlotEngine;

/** A new game with seat 3 dealing, so seat 0 bids and leads first. */
function blot(array $options = []): array
{
    $state = (new BlotEngine)->initialState(new GameSetup(4, $options), new SecureRandom);
    $state['dealer'] = 3;
    $state['to_act'] = 0;
    $state['leader'] = 0;

    return $state;
}

function blotAct(array $state, string $type, array $payload = [], ?int $seat = null): array
{
    return (new BlotEngine)->apply($state, $seat ?? $state['to_act'], new Action($type, $payload), new SecureRandom)->state;
}

/** Seat 0 bids $value in $suit with these hands, everyone else passes: the first trick is about to start. */
function blotPlaying(array $hands, string $suit = 'H', int $value = 8): array
{
    $state = blot();
    $state['hands'] = $hands;
    $state = blotAct($state, 'bid', ['value' => $value, 'suit' => $suit]);

    return blotAct(blotAct(blotAct($state, 'pass'), 'pass'), 'pass');
}

const BLOT_HANDS = [
    ['JH', '9H', 'AH', 'TH', 'KH', 'QH', '8H', '7H'],
    ['AS', 'TS', 'KS', 'QS', 'JS', '9S', '8S', '7S'],
    ['AD', 'TD', 'KD', 'QD', 'JD', '9D', '8D', '7D'],
    ['AC', 'TC', 'KC', 'QC', 'JC', '9C', '8C', '7C'],
];

function cardsOf(array $state, int $seat): array
{
    return array_map(fn (Action $a) => $a->payload['card'], (new BlotEngine)->legalActions($state, $seat));
}

it('deals eight cards each and opens the bidding left of the dealer', function () {
    $state = (new BlotEngine)->initialState(new GameSetup(4), new SecureRandom);
    $first = ($state['dealer'] + 1) % 4;

    expect(array_map('count', $state['hands']))->toBe([8, 8, 8, 8])
        ->and(array_unique(array_merge(...$state['hands'])))->toHaveCount(32)
        ->and($state['phase'])->toBe('bidding')
        ->and((new BlotEngine)->seatsToAct($state))->toBe([$first])
        ->and(array_map(fn (Action $a) => $a->toArray(), (new BlotEngine)->legalActions($state, $first)))
        ->toBe([['type' => 'bid', 'max' => 30, 'min' => 8], ['type' => 'pass']]);
});

it('accepts only higher bids with a suit', function () {
    $engine = new BlotEngine;
    $state = blot();

    expect($engine->isLegal($state, 0, new Action('bid', ['value' => 7, 'suit' => 'S'])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('bid', ['value' => 9, 'suit' => 'X'])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('bid', ['value' => '9', 'suit' => 'S'])))->toBeFalse()
        ->and($engine->isLegal($state, 1, new Action('bid', ['value' => 9, 'suit' => 'S'])))->toBeFalse();

    $state = blotAct($state, 'bid', ['value' => 9, 'suit' => 'S']);
    expect($state['contract'])->toBe(['seat' => 0, 'team' => 0, 'value' => 9, 'suit' => 'S', 'doubled' => 0])
        ->and($engine->legalActions($state, 1)[0]->payload['min'])->toBe(10)
        ->and(fn () => blotAct($state, 'bid', ['value' => 9, 'suit' => 'H']))->toThrow(IllegalAction::class);
});

it('lets only opponents double and the bidders redouble, which starts the play', function () {
    $engine = new BlotEngine;
    $state = blotAct(blot(), 'bid', ['value' => 8, 'suit' => 'S']);
    expect(array_map(fn (Action $a) => $a->type, $engine->legalActions($state, 1)))->toBe(['bid', 'pass', 'double']);

    $state = blotAct($state, 'double');
    expect(array_map(fn (Action $a) => $a->type, $engine->legalActions($state, 2)))->toBe(['pass', 'redouble']);

    $state = blotAct($state, 'redouble');
    expect($state['phase'])->toBe('playing')
        ->and($state['contract']['doubled'])->toBe(2)
        ->and($state['to_act'])->toBe(0);
});

it('redeals with the next dealer when everyone passes', function () {
    $state = blot();
    for ($i = 0; $i < 4; $i++) {
        $state = blotAct($state, 'pass');
    }

    expect($state['dealer'])->toBe(0)
        ->and($state['hand_no'])->toBe(2)
        ->and($state['to_act'])->toBe(1)
        ->and($state['phase'])->toBe('bidding');
});

it('starts the play after three passes, the trump set and the first player leading', function () {
    $state = blotPlaying(BLOT_HANDS, 'H');

    expect($state['phase'])->toBe('playing')
        ->and($state['to_act'])->toBe(0)
        ->and($state['belote'])->toBe(0)
        ->and(cardsOf($state, 0))->toHaveCount(8);
});

it('makes players follow suit, and overtrump when trumps are led', function () {
    $hands = [
        ['JH', '7S', '8S', '9S', 'TS', 'QS', 'KS', 'AS'],
        ['9H', '7H', 'AD', '7C', '8C', '9C', 'TC', 'JC'],
        ['TH', 'KD', 'QD', '7D', '8D', '9D', 'TD', 'JD'],
        ['QH', 'AH', 'KH', '8H', 'AC', 'QC', 'KC', '8D'],
    ];
    $state = blotPlaying($hands, 'D');
    $state = blotAct($state, 'play', ['card' => '7S']);

    // Seat 1 has no spades and an opponent wins: it must trump (diamonds).
    expect(cardsOf($state, 1))->toBe(['AD']);

    $state = blotPlaying($hands, 'H');
    $state = blotAct($state, 'play', ['card' => 'JH']);
    // Trumps led by the jack: nobody can beat it, so any heart will do.
    expect(cardsOf($state, 1))->toBe(['9H', '7H']);
});

it('requires a higher trump when trumping over an opponent, and frees a player whose partner wins', function () {
    $hands = [
        ['AS', '7D', '8D', '9D', 'TD', 'JD', 'QD', 'KD'],
        ['9H', '7C', '8C', '9C', 'TC', 'JC', 'QC', 'KC'],
        ['7H', 'JH', 'AC', 'AD', '7S', '8S', '9S', 'TS'],
        ['8H', 'QH', 'KH', 'AH', 'TH', 'QS', 'KS', 'JS'],
    ];
    $state = blotPlaying($hands, 'H');
    $state = blotAct($state, 'play', ['card' => 'AS']);
    // Seat 1 has no spades: an opponent (seat 0) is winning, so it must trump.
    expect(cardsOf($state, 1))->toBe(['9H']);
    $state = blotAct($state, 'play', ['card' => '9H']);

    // Seat 2 follows spades (the led suit), whoever is winning.
    expect(cardsOf($state, 2))->toBe(['7S', '8S', '9S', 'TS']);
    $state = blotAct($state, 'play', ['card' => '7S']);

    // Seat 3 has spades too.
    expect(cardsOf($state, 3))->toBe(['QS', 'KS', 'JS']);

    // Void of spades with the partner (seat 1) winning: anything goes.
    $state['hands'][3] = ['8H', 'QH', 'AC'];
    expect(cardsOf($state, 3))->toBe(['8H', 'QH', 'AC']);
});

it('makes a player trump under when they can\'t beat the opponent\'s trump', function () {
    $state = blotPlaying(BLOT_HANDS, 'C');
    $state['hands'] = [['AS'], ['JC'], ['7C', '8D'], ['QD']];
    $state = blotAct(blotAct($state, 'play', ['card' => 'AS']), 'play', ['card' => 'JC']);

    // Seat 2's partner (seat 0) is losing to seat 1's jack of trumps: it must still trump.
    expect(cardsOf($state, 2))->toBe(['7C']);
});

it('gives the trick to the highest trump, or the highest card of the led suit', function () {
    expect(BlotCards::winning(['7S', 'AS', 'TS', 'KS'], 'H'))->toBe(1)
        ->and(BlotCards::winning(['7S', 'AS', '7H', 'KS'], 'H'))->toBe(2)
        ->and(BlotCards::winning(['AH', '9H', 'JH', 'TH'], 'H'))->toBe(2)
        ->and(BlotCards::winning(['TS', 'AD', 'KS', 'JS'], 'H'))->toBe(0)
        ->and(BlotCards::points('JH', 'H'))->toBe(20)
        ->and(BlotCards::points('9H', 'H'))->toBe(14)
        ->and(BlotCards::points('JS', 'H'))->toBe(2)
        ->and(BlotCards::points('9S', 'H'))->toBe(0);
});

it('finds sequences and four of a kind', function () {
    $found = BlotCards::declarations(['7S', '8S', '9S', 'JD', 'QD', 'KD', 'AD', 'JH', 'JC', 'JS']);
    $byKind = array_column($found, 'points', 'kind');

    expect($byKind)->toBe(['terz' => 20, 'fifty' => 50, 'carre' => 200])
        ->and(BlotCards::declarations(['9H', 'TH', 'JH', 'QH', 'KH']))->toHaveCount(1)
        ->and(BlotCards::declarations(['9H', 'TH', 'JH', 'QH', 'KH'])[0]['points'])->toBe(100)
        ->and(BlotCards::declarations(['7H', '7S', '7D', '7C']))->toBe([]);
});

/** Leave one card each, with the earlier tricks' points, and play the last trick. */
function lastTrick(array $state, array $cards, array $points, array $tricks): array
{
    $state['hands'] = array_map(fn (string $c) => [$c], $cards);
    $state['points'] = $points;
    $state['tricks'] = $tricks;
    $state['declarations_shown'] = true;
    foreach ($cards as $_) {
        $state = blotAct($state, 'play', ['card' => $state['hands'][$state['to_act']][0]]);
    }

    return $state;
}

it('scores a made contract to each team, with the last trick, declarations and belote', function () {
    // Seat 0 (team 0) has a hundred in hearts and belote; nobody else declares.
    $state = blotPlaying([
        ['JH', '9H', 'TH', 'QH', 'KH', '7D', '8C', '7S'],
        ['AS', 'KS', 'JS', '9D', 'AC', 'TC', '9S', '8D'],
        ['AH', '8H', '7H', 'QS', 'TS', 'AD', 'TD', 'KC'],
        ['QD', 'JD', 'KD', '8S', 'QC', 'JC', '9C', '7C'],
    ], 'H', 10);
    expect(array_column($state['declarations'][0], 'kind'))->toBe(['hundred']);

    $state = lastTrick($state, ['7D', '8D', 'AD', 'QD'], [100, 41], [5, 2]);

    // Team 0 takes the last trick (A♦ 11 + Q♦ 3 + 10): 124 in tricks, + 100 + 20 belote.
    expect($state['last_hand'])->toMatchArray(['made' => true, 'tricks' => [124, 41], 'declarations' => [100, 0], 'belote' => [20, 0], 'scored' => [244, 41]])
        ->and($state['scores'])->toBe([244, 41])
        ->and($state['phase'])->toBe('over')
        ->and((new BlotEngine)->pauseMs($state))->toBe(5000);
});

it('gives everything to the defenders when the contract fails', function () {
    $state = blotPlaying(BLOT_HANDS, 'S', 16);
    $state['declarations'] = [[], [], [], []];
    $state['belote'] = null;
    $state = lastTrick($state, ['7D', '8D', 'AD', 'QD'], [40, 98], [3, 4]);

    expect($state['last_hand'])->toMatchArray(['made' => false, 'tricks' => [64, 98]])
        ->and($state['scores'])->toBe([0, 162]);
});

it('counts all eight tricks as 250 (kaput)', function () {
    $state = blotPlaying(BLOT_HANDS, 'S', 8);
    $state['declarations'] = [[], [], [], []];
    $state['belote'] = null;
    $state = lastTrick($state, ['AD', '8D', '7D', 'QD'], [128, 0], [7, 0]);

    expect($state['last_hand'])->toMatchArray(['made' => true, 'kaput' => true, 'tricks' => [250, 0]])
        ->and($state['scores'])->toBe([250, 0]);
});

it('doubles everything for the team that wins a doubled hand', function () {
    $state = blot();
    $state['hands'] = BLOT_HANDS;
    $state = blotAct($state, 'bid', ['value' => 8, 'suit' => 'S']);
    $state = blotAct($state, 'double');
    $state = blotAct(blotAct(blotAct($state, 'pass'), 'pass'), 'pass');
    $state['declarations'] = [[], [], [], []];
    $state['belote'] = null;
    $state = lastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);

    expect($state['last_hand']['made'])->toBeTrue()
        ->and($state['scores'])->toBe([324, 0]);
});

it('lets only the best declaration\'s team score its declarations', function () {
    $state = blotPlaying(BLOT_HANDS, 'S', 8);
    $state['declarations'] = [
        [['kind' => 'terz', 'cards' => [], 'points' => 20, 'rank' => 2005]],
        [['kind' => 'fifty', 'cards' => [], 'points' => 50, 'rank' => 5004]],
        [['kind' => 'terz', 'cards' => [], 'points' => 20, 'rank' => 2007]],
        [],
    ];
    $state['belote'] = null;
    $state = lastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);

    expect($state['last_hand']['declarations'])->toBe([0, 50]);
});

it('ends when a team reaches the target, and plays on through a tie', function () {
    $engine = new BlotEngine;
    $state = blotPlaying(BLOT_HANDS, 'S', 8);
    $state['declarations'] = [[], [], [], []];
    $state['belote'] = null;
    $state['scores'] = [200, 280];
    $state = lastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);

    // 200 + 124 = 324 against 280 + 38 = 318.
    expect($engine->outcome($state)?->winners)->toBe([0, 2])
        ->and($engine->outcome($state)?->scores)->toBe([324, 318, 324, 318]);

    $tie = blotPlaying(BLOT_HANDS, 'S', 8);
    $tie['declarations'] = [[], [], [], []];
    $tie['belote'] = null;
    $tie['scores'] = [200, 286];
    $tie = lastTrick($tie, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);
    expect($tie['scores'])->toBe([324, 324])->and($engine->outcome($tie))->toBeNull();
});

it('shows a player only their own cards, and others\' declarations after the first trick', function () {
    $engine = new BlotEngine;
    $state = blotPlaying(BLOT_HANDS, 'H');
    $view = $engine->viewFor($state, 1);

    expect($view['hand'])->toBe(BLOT_HANDS[1])
        ->and($view)->not->toHaveKey('hands')
        ->and($view['hand_sizes'])->toBe([8, 8, 8, 8])
        ->and($view['declarations'][0])->toBe([])
        ->and($view['declarations'][1])->not->toBe([])
        ->and($view['trump'])->toBe('H')
        ->and(json_encode($view))->not->toContain('"JH"');

    $state['declarations_shown'] = true;
    expect($engine->viewFor($state, 1)['declarations'][0])->not->toBe([]);
});

it('knows the partnerships', function () {
    expect((new BlotEngine)->teamOf(0, 4))->toBe([0, 2])
        ->and((new BlotEngine)->teamOf(3, 4))->toBe([1, 3]);
});

it('passes or plays a playable card for a player who runs out of time', function () {
    $engine = new BlotEngine;
    expect($engine->timeoutAction(blot(), 0)->type)->toBe('pass');

    $state = blotPlaying(BLOT_HANDS, 'H');
    expect($engine->isLegal($state, 0, $engine->timeoutAction($state, 0)))->toBeTrue();
});

it('lets bots of every difficulty play whole games', function (BotDifficulty $difficulty) {
    $engine = new BlotEngine;
    $bot = new BlotBot;
    $random = new SecureRandom;
    $state = $engine->initialState(new GameSetup(4, ['target' => 301]), $random);

    for ($step = 0; $step < 3000 && $engine->outcome($state) === null; $step++) {
        if ($engine->pauseMs($state) !== null) {
            $state = $engine->resume($state, $random)->state;

            continue;
        }
        $seat = $engine->seatsToAct($state)[0];
        $action = $bot->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), $difficulty, $random);
        expect($engine->isLegal($state, $seat, $action))->toBeTrue();
        $state = $engine->apply($state, $seat, $action, $random)->state;
    }

    expect($engine->outcome($state))->not->toBeNull()
        ->and(max($state['scores']))->toBeGreaterThanOrEqual(301);
})->with([BotDifficulty::Easy, BotDifficulty::Medium, BotDifficulty::Hard]);
