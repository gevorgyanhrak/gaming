<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\SecureRandom;
use App\Games\Blot\Bot\BlotBot;
use App\Games\Blot\Engine\BlotCards;
use App\Games\Blot\Engine\BlotEngine;

/*
 * Bazar Blot, per docs/games/blot-rules.md. Seat 3 deals in these tests, so seat 0 bids and leads first.
 */

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

function blotTypes(array $state, ?int $seat = null): array
{
    return array_map(fn (Action $a) => $a->type, (new BlotEngine)->legalActions($state, $seat ?? $state['to_act']));
}

/** Seat 0 bids $value in $suit (N = no-trump; 'kaput' for a kaput bid), then: pass, pass, pass — or contra (seat 1) and an answer (seat 2). */
function blotContract(array $hands, string $suit = 'H', int|string $value = 8, int $doubled = 0): array
{
    $state = blot();
    $state['hands'] = $hands;
    $state = $value === 'kaput' ? blotAct($state, 'kaput', ['suit' => $suit]) : blotAct($state, 'bid', ['value' => $value, 'suit' => $suit]);
    if ($doubled === 0) {
        return blotAct(blotAct(blotAct($state, 'pass'), 'pass'), 'pass');
    }
    $state = blotAct($state, 'contra');

    return blotAct($state, $doubled === 2 ? 'recontra' : 'pass');
}

const HANDS = [
    ['JH', '9H', 'AH', 'TH', 'KH', 'QH', '8H', '7H'],
    ['AS', 'TS', 'KS', 'QS', 'JS', '9S', '8S', '7S'],
    ['AD', 'TD', 'KD', 'QD', 'JD', '9D', '8D', '7D'],
    ['AC', 'TC', 'KC', 'QC', 'JC', '9C', '8C', '7C'],
];

function cardsOf(array $state, ?int $seat = null): array
{
    $seat ??= $state['to_act'];

    return array_map(fn (Action $a) => $a->payload['card'], array_values(array_filter((new BlotEngine)->legalActions($state, $seat), fn (Action $a) => $a->type === 'play')));
}

/**
 * Jump to the last trick: one card each, these card points and tricks so far, no declarations or blot
 * unless given, everyone past their first card. Seat 0 leads it.
 */
function blotLastTrick(array $state, array $cards, array $points, array $tricks, array $declared = [[], [], [], []], ?int $belote = null): array
{
    $state['hands'] = array_map(fn (string $c) => [$c], $cards);
    $state['points'] = $points;
    $state['tricks'] = $tricks;
    $state['declared'] = $declared;
    $state['belote'] = $belote;
    $state['declarations_shown'] = true;
    $state['played_first'] = [true, true, true, true];
    $state['to_act'] = 0;
    foreach ($cards as $_) {
        $state = blotAct($state, 'play', ['card' => $state['hands'][$state['to_act']][0]]);
    }

    return $state;
}

/** A declaration as the server records it, for setting up comparisons. */
function decl(array $hand, ?string $trump, int $i = 0): array
{
    return BlotCards::declarations($hand, $trump)[$i];
}

// ── Cards and tricks ──────────────────────────────────────────────────────────────────────────────

it('values every card in trump, plain and no-trump games', function () {
    $trump = ['J' => 20, '9' => 14, 'A' => 11, 'T' => 10, 'K' => 4, 'Q' => 3, '8' => 0, '7' => 0];
    $plain = ['A' => 11, 'T' => 10, 'K' => 4, 'Q' => 3, 'J' => 2, '9' => 0, '8' => 0, '7' => 0];
    $noTrump = ['A' => 19, 'T' => 10, 'K' => 4, 'Q' => 3, 'J' => 2, '9' => 0, '8' => 0, '7' => 0];
    foreach ($trump as $rank => $points) {
        expect(BlotCards::points("{$rank}H", 'H'))->toBe($points)
            ->and(BlotCards::points("{$rank}S", 'H'))->toBe($plain[$rank])
            ->and(BlotCards::points("{$rank}S", null))->toBe($noTrump[$rank]);
    }
    // 152 card points in every kind of game.
    $deck = array_merge(...HANDS);
    expect(array_sum(array_map(fn ($c) => BlotCards::points($c, 'H'), $deck)))->toBe(152)
        ->and(array_sum(array_map(fn ($c) => BlotCards::points($c, null), $deck)))->toBe(152);
});

it('ranks trumps J 9 A 10 K Q 8 7 and other suits A 10 K Q J 9 8 7', function () {
    $trumpOrder = ['JH', '9H', 'AH', 'TH', 'KH', 'QH', '8H', '7H'];
    $plainOrder = ['AS', 'TS', 'KS', 'QS', 'JS', '9S', '8S', '7S'];
    for ($i = 0; $i < 7; $i++) {
        expect(BlotCards::strength($trumpOrder[$i], 'H', 'H'))->toBeGreaterThan(BlotCards::strength($trumpOrder[$i + 1], 'H', 'H'))
            ->and(BlotCards::strength($plainOrder[$i], 'S', 'H'))->toBeGreaterThan(BlotCards::strength($plainOrder[$i + 1], 'S', 'H'));
    }
});

it('gives the trick to the highest trump, or the highest card of the led suit', function () {
    expect(BlotCards::winning(['7S', 'AS', 'TS', 'KS'], 'H'))->toBe(1)
        ->and(BlotCards::winning(['7S', 'AS', '7H', 'KS'], 'H'))->toBe(2)
        ->and(BlotCards::winning(['AH', '9H', 'JH', 'TH'], 'H'))->toBe(2)
        ->and(BlotCards::winning(['TS', 'AD', 'KS', 'JS'], 'H'))->toBe(0)
        // No-trump: hearts are just another suit.
        ->and(BlotCards::winning(['7S', 'AS', 'JH', 'KS'], null))->toBe(1);
});

it('adds 10 for the last trick, to the team that takes it', function () {
    $state = blotContract(HANDS, 'S', 8);
    $state = blotLastTrick($state, ['7D', 'AD', '8D', 'QD'], [100, 38], [5, 2]);

    // Seat 1 (team 1) wins A♦: 11 + 3 = 14, + 10 for the last trick.
    expect($state['last_trick'])->toMatchArray(['winner' => 1, 'points' => 24])
        ->and($state['last_hand']['card_points'])->toBe([100, 62])
        ->and($state['last_hand']['tricks'])->toBe([5, 3]);
});

it('rounds card points to sai, more than a half up', function () {
    expect(BlotCards::sai(15))->toBe(1)
        ->and(BlotCards::sai(16))->toBe(2)
        ->and(BlotCards::sai(38))->toBe(4)
        ->and(BlotCards::sai(124))->toBe(12)
        ->and(BlotCards::sai(125))->toBe(12)
        ->and(BlotCards::sai(126))->toBe(13)
        ->and(BlotCards::sai(162))->toBe(16);
});

// ── Play ──────────────────────────────────────────────────────────────────────────────────────────

it('makes players follow suit with any card of it', function () {
    $state = blotContract([
        ['AS', '7D', '8D', '9D', 'TD', 'JD', 'QD', 'KD'],
        ['9H', '7S', 'KS', '7C', '8C', '9C', 'TC', 'JC'],
        ['7H', 'JH', 'AC', 'AD', '8S', 'QS', '9S', 'TS'],
        ['8H', 'QH', 'KH', 'AH', 'TH', 'JS', 'KC', 'QC'],
    ], 'H');
    $state = blotAct($state, 'play', ['card' => 'AS']);

    expect(cardsOf($state))->toBe(['7S', 'KS']);
});

it('makes a player without the suit trump, beating the trump on the table when they can', function () {
    $hands = [
        ['AS', '7D', '8D', '9D', 'TD', 'JD', 'QD', 'KD'],
        ['9H', '7H', 'AD', '7C', '8C', '9C', 'TC', 'JC'],
        ['TH', 'JH', 'AC', 'KC', '8S', 'QS', '9S', 'TS'],
        ['8H', 'QH', 'KH', 'AH', '7S', 'JS', 'KS', 'QC'],
    ];
    $state = blotContract($hands, 'H');
    $state = blotAct($state, 'play', ['card' => 'AS']);
    // Seat 1 has no spades and an opponent wins: it must trump.
    expect(cardsOf($state))->toBe(['9H', '7H']);
    $state = blotAct($state, 'play', ['card' => '7H']);
    // Seat 2 follows spades.
    $state = blotAct($state, 'play', ['card' => '8S']);
    expect(cardsOf($state))->toBe(['7S', 'JS', 'KS']);

    // Without spades, seat 3's partner (seat 1) is winning: any card.
    $state['hands'][3] = ['8H', 'QH', 'QC'];
    expect(cardsOf($state))->toBe(['8H', 'QH', 'QC']);
});

it('makes a player beat an opponent\'s trump if they can, otherwise play any trump', function () {
    $state = blotContract(HANDS, 'C');
    $state['hands'] = [['AS'], ['9C'], ['7C', 'JC', '8D'], ['QD']];
    $state = blotAct(blotAct($state, 'play', ['card' => 'AS']), 'play', ['card' => '9C']);
    expect(cardsOf($state))->toBe(['JC']);

    $state['hands'][2] = ['7C', '8C', '8D'];
    expect(cardsOf($state))->toBe(['7C', '8C']);
});

it('lets any card follow when the suit is missing in no-trump', function () {
    $state = blotContract(HANDS, 'N');
    $state['hands'] = [['AS'], ['9C', 'JH'], ['7C'], ['QD']];
    $state = blotAct($state, 'play', ['card' => 'AS']);

    expect(cardsOf($state))->toBe(['9C', 'JH']);
});

// ── Bazar ─────────────────────────────────────────────────────────────────────────────────────────

it('opens the bidding left of the dealer with bids from 8, kaput and pass', function () {
    $state = (new BlotEngine)->initialState(new GameSetup(4), new SecureRandom);
    $first = ($state['dealer'] + 1) % 4;

    expect(array_map('count', $state['hands']))->toBe([8, 8, 8, 8])
        ->and(array_unique(array_merge(...$state['hands'])))->toHaveCount(32)
        ->and((new BlotEngine)->seatsToAct($state))->toBe([$first])
        ->and((new BlotEngine)->legalActions($state, $first)[0]->toArray())->toBe(['type' => 'bid', 'max' => 24, 'min' => 8])
        ->and(blotTypes($state, $first))->toBe(['bid', 'kaput', 'kaput', 'kaput', 'kaput', 'kaput', 'pass']);
});

it('takes only higher bids with a suit or no-trump, from anyone including a partner', function () {
    $engine = new BlotEngine;
    $state = blot();
    expect($engine->isLegal($state, 0, new Action('bid', ['value' => 7, 'suit' => 'S'])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('bid', ['value' => 25, 'suit' => 'S'])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('bid', ['value' => 9, 'suit' => 'X'])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('bid', ['value' => '9', 'suit' => 'S'])))->toBeFalse()
        ->and($engine->isLegal($state, 0, new Action('bid', ['value' => 9, 'suit' => 'N'])))->toBeTrue();

    $state = blotAct($state, 'bid', ['value' => 9, 'suit' => 'N']);
    expect($state['contract'])->toMatchArray(['seat' => 0, 'team' => 0, 'value' => 9, 'suit' => null, 'kaput' => false])
        ->and(fn () => blotAct($state, 'bid', ['value' => 9, 'suit' => 'H']))->toThrow(IllegalAction::class);
    $state = blotAct(blotAct($state, 'pass'), 'bid', ['value' => 11, 'suit' => 'S']);   // the partner raises
    expect($state['contract'])->toMatchArray(['seat' => 2, 'team' => 0, 'value' => 11]);
});

it('lets nobody bid after kaput except with a contra', function () {
    $state = blotAct(blot(), 'kaput', ['suit' => 'D']);

    expect($state['contract'])->toMatchArray(['value' => 25, 'kaput' => true, 'suit' => 'D'])
        ->and(blotTypes($state))->toBe(['pass', 'contra']);
});

it('ends the bidding with a contra, leaving the contract pair recontra or pass', function () {
    $state = blotAct(blot(), 'bid', ['value' => 8, 'suit' => 'S']);
    // Only opponents can contra; the partner (seat 2) can't.
    expect(blotTypes($state, 1))->toContain('contra');
    $passed = blotAct($state, 'pass');
    expect(blotTypes($passed, 2))->not->toContain('contra');

    $state = blotAct($state, 'contra');
    expect($state['phase'])->toBe('answer')
        ->and($state['contract']['doubled'])->toBe(1)
        ->and($state['to_act'])->toBe(2)
        ->and(blotTypes($state))->toBe(['recontra', 'pass']);
});

it('plays a contra\'d and a recontra\'d contract', function () {
    $contra = blotContract(HANDS, 'S', 8, doubled: 1);
    expect($contra['phase'])->toBe('playing')->and($contra['contract']['doubled'])->toBe(1);

    $recontra = blotContract(HANDS, 'S', 8, doubled: 2);
    expect($recontra['phase'])->toBe('playing')->and($recontra['contract']['doubled'])->toBe(2);

    // After a contra nobody bids higher.
    $state = blotAct(blotAct(blot(), 'bid', ['value' => 8, 'suit' => 'S']), 'contra');
    expect(blotTypes($state))->toBe(['recontra', 'pass']);
});

it('redeals with the next dealer when everyone passes, and plays after three passes', function () {
    $state = blot();
    for ($i = 0; $i < 4; $i++) {
        $state = blotAct($state, 'pass');
    }
    expect($state['dealer'])->toBe(0)->and($state['hand_no'])->toBe(2)->and($state['to_act'])->toBe(1)->and($state['phase'])->toBe('bidding');

    $state = blotContract(HANDS, 'H');
    expect($state['phase'])->toBe('playing')->and($state['to_act'])->toBe(0)->and($state['belote'])->toBe(0);
});

// ── Declarations ──────────────────────────────────────────────────────────────────────────────────

it('finds terz, 50, 100 and four of a kind from the real cards', function () {
    $hand = ['7S', '8S', '9S', 'JD', 'QD', 'KD', 'AD', 'JH', 'JC', 'JS'];
    $found = BlotCards::declarations($hand, 'H');

    expect(array_column($found, 'points', 'kind'))->toBe(['terz' => 20, 'fifty' => 50, 'carre' => 200])
        ->and(BlotCards::declarations(['9H', 'TH', 'JH', 'QH', 'KH', 'AH'], 'S'))->toHaveCount(1)
        ->and(BlotCards::declarations(['9H', 'TH', 'JH', 'QH', 'KH', 'AH'], 'S')[0]['points'])->toBe(100)
        // Not a terz: a gap, or three cards of different suits.
        ->and(BlotCards::declarations(['7S', '8S', 'TS'], 'H'))->toBe([])
        ->and(BlotCards::declarations(['7S', '8H', '9S'], 'H'))->toBe([])
        ->and(BlotCards::declarations(['7H', '7S', '7D', '7C'], 'H'))->toBe([]);
});

it('values four of a kind by the kind of game', function () {
    $four = fn (string $rank, ?string $trump) => BlotCards::declarations(["{$rank}S", "{$rank}H", "{$rank}D", "{$rank}C"], $trump)[0]['points'] ?? 0;

    expect([$four('J', 'H'), $four('9', 'H'), $four('A', 'H'), $four('T', 'H'), $four('K', 'H'), $four('Q', 'H'), $four('8', 'H')])
        ->toBe([200, 140, 110, 100, 100, 100, 0])
        ->and([$four('A', null), $four('J', null), $four('T', null), $four('9', null)])->toBe([190, 100, 100, 0]);
});

it('lets a player declare only their own combinations, once each, before their first card', function () {
    $engine = new BlotEngine;
    $state = blotContract([
        ['7S', '8S', '9S', 'AH', 'KD', 'QC', 'TC', '7C'],
        ['AS', 'TS', 'KS', 'QS', 'JD', '9D', '8D', '7D'],
        ['JH', '9H', 'TH', 'KH', 'QH', 'AD', 'TD', 'JC'],
        ['8H', '7H', 'JS', 'QD', 'AC', 'KC', '9C', '8C'],
    ], 'H');

    expect(blotTypes($state))->toContain('declare')
        ->and($engine->viewFor($state, 0)['my_declarations'][0])->toMatchArray(['kind' => 'terz', 'points' => 20, 'declared' => false]);
    // A declaration that isn't in the hand (another seat's index or none) can't be made.
    expect(fn () => blotAct($state, 'declare', ['index' => 1]))->toThrow(IllegalAction::class);

    $state = blotAct($state, 'declare', ['index' => 0]);
    expect($state['declared'][0])->toHaveCount(1)
        ->and(blotTypes($state))->not->toContain('declare')
        ->and(fn () => blotAct($state, 'declare', ['index' => 0]))->toThrow(IllegalAction::class);

    // Seat 1 plays without declaring its 50: the chance is gone.
    $state = blotAct($state, 'play', ['card' => '7S']);
    expect(blotTypes($state))->toContain('declare');
    $state = blotAct($state, 'play', ['card' => 'AS']);
    expect($state['declared'][1])->toBe([])
        ->and(blotTypes($state))->toContain('declare');   // seat 2 still may
});

it('shows a declaration\'s exact cards to every player as soon as it is announced, and no other card', function () {
    $engine = new BlotEngine;
    $state = blotContract([
        ['7S', '8S', '9S', 'AH', 'KD', 'QC', 'TC', '7C'],
        ['AS', 'TS', 'KS', 'QS', 'JD', '9D', '8D', '7D'],
        ['JH', '9H', 'TH', 'KH', 'QH', 'AD', 'TD', 'JC'],
        ['8H', '7H', 'JS', 'QD', 'AC', 'KC', '9C', '8C'],
    ], 'H');
    $transition = $engine->apply($state, 0, new Action('declare', ['index' => 0]), new SecureRandom);
    $state = $transition->state;

    expect($transition->events[0])->toBe(['type' => 'declare', 'seat' => 0, 'kind' => 'terz', 'points' => 20, 'cards' => ['7S', '8S', '9S']]);
    foreach ([0, 1, 2, 3, null] as $seat) {
        $view = $engine->viewFor($state, $seat);
        expect($view['declared'][0])->toBe([['kind' => 'terz', 'points' => 20, 'cards' => ['7S', '8S', '9S']]])
            ->and($view)->not->toHaveKeys(['hands', 'available']);
        if ($seat !== 0) {
            // Nothing else from seat 0's hand.
            foreach (['AH', 'KD', 'QC', 'TC', '7C'] as $card) {
                expect(json_encode($view))->not->toContain("\"{$card}\"");
            }
        }
    }
    // Seat 1 hasn't declared its 50: nobody but seat 1 sees those cards.
    expect(json_encode($engine->viewFor($state, 2)))->not->toContain('"KS"');
});

it('keeps blot secret until both its cards are played', function () {
    $engine = new BlotEngine;
    $state = blotContract(HANDS, 'H');   // seat 0 holds K♥ and Q♥

    expect($engine->viewFor($state, 1)['belote'])->toBeNull()
        ->and($engine->viewFor($state, 0)['belote'])->toBe(0);
    $state['hands'][0] = array_values(array_diff($state['hands'][0], ['KH']));
    expect($engine->viewFor($state, 1)['belote'])->toBeNull();
    $state['hands'][0] = array_values(array_diff($state['hands'][0], ['QH']));
    expect($engine->viewFor($state, 1)['belote'])->toBe(0);
});

it('counts only the declarations of the pair with the best one', function (array $mine, array $theirs, int $winner) {
    $state = blotContract(HANDS, 'S', 8);
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2], [$mine, $theirs, [], []]);

    $counted = array_values(array_unique(array_map(fn ($d) => $d['seat'] % 2, array_filter($state['last_hand']['declarations'], fn ($d) => $d['counted']))));
    expect($counted)->toBe([$winner]);
})->with([
    '50 beats terz' => [[decl(['7H', '8H', '9H'], 'S')], [decl(['7C', '8C', '9C', 'TC'], 'S')], 1],
    'four of a kind beats a 100' => [[decl(['QH', 'QS', 'QD', 'QC'], 'S')], [decl(['8C', '9C', 'TC', 'JC', 'QC'], 'S')], 0],
    'the higher top card' => [[decl(['8H', '9H', 'TH'], 'S')], [decl(['7C', '8C', '9C'], 'S')], 0],
    'the trump sequence' => [[decl(['8H', '9H', 'TH'], 'S')], [decl(['8S', '9S', 'TS'], 'S')], 1],
    'equal: the first from the dealer\'s left' => [[decl(['8H', '9H', 'TH'], 'S')], [decl(['8C', '9C', 'TC'], 'S')], 0],
    'jacks beat nines' => [[decl(['9H', '9S', '9D', '9C'], 'S')], [decl(['JH', 'JS', 'JD', 'JC'], 'S')], 1],
]);

it('gives blot to its holder\'s pair whatever happens to the contract', function () {
    $state = blotContract(HANDS, 'S', 16);
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2], belote: 1);

    // Team 0 fails its 16; team 1 holds blot.
    expect($state['last_hand']['made'])->toBeFalse()
        ->and($state['last_hand']['belote_sai'])->toBe([0, 2])
        ->and($state['last_hand']['scored'])->toBe([0, 16 + 16 + 2]);
});

// ── Scoring a deal ────────────────────────────────────────────────────────────────────────────────

it('scores a made contract: bid + 16 less the defenders\' sai, the defenders their sai', function () {
    $state = blotContract(HANDS, 'S', 10);
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);

    // Team 0 takes the last trick: 124 card points; defenders 38 → 4 sai.
    expect($state['last_hand'])->toMatchArray(['made' => true, 'card_points' => [124, 38], 'card_sai' => [12, 4], 'bid_sai' => 10, 'scored' => [22, 4], 'totals' => [22, 4]])
        ->and($state['phase'])->toBe('over')
        ->and($state['result'])->toBeNull();
});

it('counts the bidders\' declarations towards the contract and the score', function () {
    $terz = decl(['7H', '8H', '9H'], 'S');
    $state = blotContract(HANDS, 'S', 13);
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [90, 48], [5, 2], [[$terz], [], [], []]);

    // 114 card points + 20 = 134 ≥ 130.
    expect($state['last_hand'])->toMatchArray(['made' => true, 'declaration_sai' => [2, 0], 'scored' => [13 + 16 - 5 + 2, 5]]);
});

it('gives everything to the defenders when the contract fails', function () {
    $state = blotContract(HANDS, 'S', 16);
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2], [[], [decl(['7C', '8C', '9C'], 'S')], [], []]);

    expect($state['last_hand'])->toMatchArray(['made' => false, 'scored' => [0, 16 + 16 + 2]])
        ->and($state['scores'])->toBe([0, 34]);
});

it('multiplies the bid for contra, recontra and no-trump', function (string $suit, int $doubled, bool $made, array $scored) {
    $state = blotContract(HANDS, $suit, $made ? 8 : 16, $doubled);
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);

    expect($state['last_hand']['scored'])->toBe($scored);
})->with([
    'contra, made: everything to the bidders' => ['S', 1, true, [8 * 2 + 16, 0]],
    'recontra, made' => ['S', 2, true, [8 * 4 + 16, 0]],
    'contra, failed' => ['S', 1, false, [0, 16 * 2 + 16]],
    'recontra, failed' => ['S', 2, false, [0, 16 * 4 + 16]],
    'no-trump, made' => ['N', 0, true, [8 * 2 + 16 - 4, 4]],
    'no-trump contra, made' => ['N', 1, true, [8 * 3 + 16, 0]],
    'no-trump recontra, failed' => ['N', 2, false, [0, 16 * 5 + 16]],
]);

it('counts 25 for taking every trick', function () {
    $state = blotContract(HANDS, 'S', 8);
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [138, 0], [7, 0]);

    expect($state['last_hand'])->toMatchArray(['made' => true, 'kaput' => true, 'scored' => [8 + 25, 0]]);
});

it('scores a kaput contract: all eight tricks, or the defenders take 25 + 16', function () {
    $made = blotContract(HANDS, 'S', 'kaput');
    $made = blotLastTrick($made, ['AD', '8D', '7D', 'QD'], [138, 0], [7, 0]);
    expect($made['last_hand'])->toMatchArray(['made' => true, 'scored' => [25 + 25, 0]])
        ->and($made['result'])->toBeNull();

    $failed = blotContract(HANDS, 'S', 'kaput');
    $failed = blotLastTrick($failed, ['8D', 'AD', '7D', 'QD'], [138, 0], [7, 0]);
    expect($failed['last_hand'])->toMatchArray(['made' => false, 'scored' => [0, 25 + 16]]);
});

it('ends the match when the bidders take no trick, against them', function () {
    $state = blotContract(HANDS, 'S', 8);
    $state = blotLastTrick($state, ['8D', 'AD', '7D', 'QD'], [0, 138], [0, 7]);

    expect($state['last_hand']['scored'])->toBe([0, 8 + 25])
        ->and((new BlotEngine)->outcome($state)?->winners)->toBe([1, 3])
        ->and((new BlotEngine)->outcome($state)?->reason)->toBe('reverse_kaput');
});

it('ends the match when a contra\'d kaput is made', function () {
    $state = blotContract(HANDS, 'S', 'kaput', doubled: 1);
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [138, 0], [7, 0]);

    expect((new BlotEngine)->outcome($state)?->winners)->toBe([0, 2])
        ->and((new BlotEngine)->outcome($state)?->reason)->toBe('kaput_contra');
});

// ── The match ─────────────────────────────────────────────────────────────────────────────────────

it('never ends the match after the first deal, even the biggest one', function () {
    // Recontra'd no-trump 24, failed: 24 × 5 + 16 = 136 sai for the defenders — far from 301.
    $state = blotContract(HANDS, 'N', 24, doubled: 2);
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);

    expect($state['scores'])->toBe([0, 136])
        ->and((new BlotEngine)->outcome($state))->toBeNull()
        ->and((new BlotEngine)->pauseMs($state))->toBe(6000);
});

it('adds the deals up and starts the next one with the next dealer', function () {
    $engine = new BlotEngine;
    $state = blotContract(HANDS, 'S', 10);
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);
    $next = $engine->resume($state, new SecureRandom)->state;

    expect($next['scores'])->toBe([22, 4])
        ->and($next['hand_no'])->toBe(2)
        ->and($next['dealer'])->toBe(0)
        ->and($next['phase'])->toBe('bidding')
        ->and($next['contract'])->toBeNull()
        ->and($next['declared'])->toBe([[], [], [], []]);
});

it('ends at the target with the higher score, and plays on through a tie', function () {
    $engine = new BlotEngine;
    $state = blotContract(HANDS, 'S', 10);
    $state['scores'] = [285, 290];
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);
    // 285 + 22 = 307 against 290 + 4 = 294.
    expect($engine->outcome($state)?->winners)->toBe([0, 2])
        ->and($engine->outcome($state)?->scores)->toBe([307, 294, 307, 294]);

    $tie = blotContract(HANDS, 'S', 10);
    $tie['scores'] = [285, 303];
    $tie = blotLastTrick($tie, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);
    expect($tie['scores'])->toBe([307, 307])->and($engine->outcome($tie))->toBeNull();

    $short = blotContract(HANDS, 'S', 10);
    $short['scores'] = [100, 290];
    $short = blotLastTrick($short, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);
    // Team 1 reaches 294 < 301: the match goes on.
    expect($engine->outcome($short))->toBeNull();
});

it('lets the target be set per table', function () {
    $state = blotContract(HANDS, 'S', 10);
    $state['target'] = 101;
    $state['scores'] = [80, 0];
    $state = blotLastTrick($state, ['AD', '8D', '7D', 'QD'], [100, 38], [5, 2]);

    expect((new BlotEngine)->outcome($state)?->winners)->toBe([0, 2]);
});

// ── Other ─────────────────────────────────────────────────────────────────────────────────────────

it('knows the partnerships', function () {
    expect((new BlotEngine)->teamOf(0, 4))->toBe([0, 2])->and((new BlotEngine)->teamOf(3, 4))->toBe([1, 3]);
});

it('passes or plays a playable card for a player who runs out of time', function () {
    $engine = new BlotEngine;
    expect($engine->timeoutAction(blot(), 0)->type)->toBe('pass');

    $state = blotContract(HANDS, 'H');
    expect($engine->isLegal($state, 0, $engine->timeoutAction($state, 0)))->toBeTrue();
});

it('lets bots of every difficulty play whole matches over many deals', function (BotDifficulty $difficulty) {
    $engine = new BlotEngine;
    $bot = new BlotBot;
    $random = new SecureRandom;
    $state = $engine->initialState(new GameSetup(4, ['target' => 301]), $random);

    for ($step = 0; $step < 20000 && $engine->outcome($state) === null; $step++) {
        if ($engine->pauseMs($state) !== null) {
            $state = $engine->resume($state, $random)->state;

            continue;
        }
        $seat = $engine->seatsToAct($state)[0];
        $action = $bot->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), $difficulty, $random);
        expect($engine->isLegal($state, $seat, $action))->toBeTrue();
        $state = $engine->apply($state, $seat, $action, $random)->state;
    }

    $outcome = $engine->outcome($state);
    expect($outcome)->not->toBeNull();
    if ($outcome->reason === 'target_reached') {
        expect($state['hand_no'])->toBeGreaterThan(1)
            ->and(max($state['scores']))->toBeGreaterThanOrEqual(301);
    }
})->with([BotDifficulty::Easy, BotDifficulty::Medium, BotDifficulty::Hard]);
