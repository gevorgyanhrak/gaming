<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Games\Blot\Bot\BlotBot;
use App\Games\Blot\Bot\CardMemory;
use App\Games\Blot\Bot\ClassicBlotBot;
use App\Games\Blot\Bot\DealSampler;
use App\Games\Blot\Bot\Simulator;
use App\Games\Blot\Engine\BlotCards;
use App\Games\Blot\Engine\BlotEngine;
use Tests\Fixtures\Blot\SeededRandom;

/**
 * Deals $mine to seat 0 (and $given to others), the rest in order, and opens the bazar left of the dealer
 * (seat 3), so seat 0 speaks first.
 *
 * @param  list<string>  $mine
 * @param  array<int, list<string>>  $given
 */
function proBazar(array $mine, array $given = []): array
{
    $engine = new BlotEngine;
    $random = new SeededRandom(5);
    $state = $engine->resume($engine->initialState(new GameSetup(4), $random), $random)->state;
    $state['hands'] = proFill([0 => $mine] + $given);
    [$state['dealer'], $state['to_act'], $state['leader']] = [3, 0, 0];

    return $state;
}

/** Fills every seat's hand to eight from the cards nobody was given. */
function proFill(array $given): array
{
    $used = array_merge(...array_values($given));
    $rest = array_values(array_diff(CardMemory::deck(), $used));
    $hands = [];
    foreach (range(0, 3) as $seat) {
        $hands[$seat] = $given[$seat] ?? [];
        while (count($hands[$seat]) < 8) {
            $hands[$seat][] = array_shift($rest);
        }
    }

    return $hands;
}

/** Applies actions in turn order: [type, payload] pairs, or a card code for a play. */
function proAct(array $state, array $steps): array
{
    $engine = new BlotEngine;
    foreach ($steps as $step) {
        [$type, $payload] = is_string($step) ? ['play', ['card' => $step]] : $step;
        $state = $engine->apply($state, (int) $state['to_act'], new Action($type, $payload), new SeededRandom(1))->state;
    }

    return $state;
}

/** The bot's choice for whoever acts now (Hard, the level AI plays at). */
function proChoice(array $state, int $seed = 3): Action
{
    $engine = new BlotEngine;
    $seat = (int) $state['to_act'];
    $action = (new BlotBot)->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), BotDifficulty::Hard, new SeededRandom($seed));
    // Combinations are announced before the first card: then the card.
    while ($action->type === 'declare') {
        $state = $engine->apply($state, $seat, $action, new SeededRandom(1))->state;
        $action = (new BlotBot)->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), BotDifficulty::Hard, new SeededRandom($seed));
    }

    return $action;
}

/**
 * A deal in play set up card by card: current hands, the finished tricks (their cards are gone), the trick
 * on the table, the contract and who acts.
 *
 * @param  array<int, list<string>>  $hands
 * @param  list<list<array{0: int, 1: string}>>  $history  [seat, card] per play, each trick led by its first
 * @param  list<array{0: int, 1: string}>  $trick
 */
function proEndgame(array $hands, array $history, array $trick, string $trump, int $team, int $toAct): array
{
    $engine = new BlotEngine;
    $random = new SeededRandom(9);
    $state = $engine->resume($engine->initialState(new GameSetup(4), $random), $random)->state;
    $tricks = array_map(function (array $plays) use ($trump) {
        $cards = array_map(fn (array $p) => ['seat' => $p[0], 'card' => $p[1]], $plays);

        return ['cards' => $cards, 'winner' => $cards[BlotCards::winning(array_column($cards, 'card'), $trump)]['seat']];
    }, $history);

    return [
        ...$state,
        'phase' => 'playing',
        'hands' => $hands,
        'contract' => ['seat' => $team, 'team' => $team, 'value' => 8, 'suit' => $trump, 'kaput' => false, 'doubled' => 0],
        'history' => $tricks,
        'trick' => array_map(fn (array $p) => ['seat' => $p[0], 'card' => $p[1]], $trick),
        'tricks' => [count(array_filter($tricks, fn ($t) => $t['winner'] % 2 === 0)), count(array_filter($tricks, fn ($t) => $t['winner'] % 2 === 1))],
        'points' => [40, 40],
        'played_first' => [true, true, true, true],
        'passes' => 3,
        'to_act' => $toAct,
        'leader' => $toAct,
    ];
}

// ── Bazar ────────────────────────────────────────────────────────────────────────────────────────────────

it('1 · bids a strong trump hand in its trump', function () {
    $action = proChoice(proBazar(['JH', '9H', 'AH', 'TH', 'KH', 'AS', 'AD', '7C']));

    expect($action->type)->toBeIn(['bid', 'kaput'])
        ->and($action->payload['suit'])->toBe('H');
});

it('2 · passes a weak hand', function () {
    expect(proChoice(proBazar(['7S', '8S', '7D', '8D', '9C', '7C', '8H', '9S']))->type)->toBe('pass');
});

it('3 · bids Boy with aces and tens and no long suit', function () {
    $action = proChoice(proBazar(['AS', 'TS', 'AH', 'TH', 'AD', 'TD', 'AC', 'KC']));

    expect($action->type)->toBe('bid')->and($action->payload['suit'])->toBe('N');
});

it('4 · bids kaput holding every top trump and side aces (in Boy: every trick is as sure, and Boy pays double)', function () {
    $action = proChoice(proBazar(['JH', '9H', 'AH', 'TH', 'KH', 'QH', 'AS', 'AD']));

    expect($action->type)->toBe('kaput')->and($action->payload['suit'])->toBeIn(['H', 'N']);
});

it('5 · doesn\'t chase a kaput it can\'t make without the trump jack and nine', function () {
    $action = proChoice(proBazar(['AH', 'TH', 'KH', 'QH', '8H', 'AS', 'AD', 'AC']));

    expect($action->type)->not->toBe('kaput');
});

it('6 · says Quansh on a contract its own trumps defeat', function () {
    $state = proAct(proBazar(['7S', '8S', '7D', '8D', '9C', '7C', '8C', '9S'], [1 => ['JH', '9H', 'AH', 'TH', 'AS', 'AD', 'AC', 'TS']]), [['bid', ['value' => 14, 'suit' => 'H']]]);

    expect(proChoice($state)->type)->toBe('contra');
});

it('13 · punishes an obviously wrong bid: a 16 on nothing in a suit it holds the top of', function () {
    $state = proAct(proBazar(['7S', '8S', '7D', '8D', '9C', '7C', '8C', '9S'], [1 => ['JD', '9D', 'AD', 'TD', 'KD', 'AS', 'AC', 'AH']]), [['bid', ['value' => 16, 'suit' => 'D']]]);

    expect(proChoice($state)->type)->toBe('contra');
});

it('7 · answers a Quansh with Sur only when the contract is safe', function () {
    $strong = proAct(proBazar(['JH', '9H', 'AH', 'TH', 'KH', 'QH', 'AS', 'AD']), [['bid', ['value' => 9, 'suit' => 'H']], ['pass', []], ['pass', []], ['contra', []]]);
    expect($strong['phase'])->toBe('answer')->and($strong['to_act'])->toBe(0)
        ->and(proChoice($strong)->type)->toBe('recontra');

    $weak = proAct(proBazar(['AH', 'TH', '8H', '7S', '8D', '9C', '7C', '8S']), [['bid', ['value' => 12, 'suit' => 'H']], ['pass', []], ['pass', []], ['contra', []]]);
    expect(proChoice($weak)->type)->toBe('pass');
});

// ── Play ─────────────────────────────────────────────────────────────────────────────────────────────────

it('8 · loads its partner\'s sure trick with its ten instead of keeping it', function () {
    // Seat 0 bid hearts; seat 1 (the bot's partner) wins the spade lead with the ace; the bot plays last.
    $state = proAct(proBazar(['8S', 'JH', '9H', 'AH', '7D', '8D', '7C', '8C'], [1 => ['AS', 'KD', 'QD', 'JD', 'KC', 'QC', 'JC', '9C'], 2 => ['7S', 'TH', 'KH', 'QH', '8H', '7H', 'AD', 'TD'], 3 => ['TS', 'KS', '9D', 'AC', 'TC', '9S', 'QS', 'JS']]), [
        ['bid', ['value' => 8, 'suit' => 'H']], ['pass', []], ['pass', []], ['pass', []], '8S', 'AS', '7S',
    ]);

    expect($state['to_act'])->toBe(3)->and(proChoice($state)->payload['card'])->toBe('TS');
});

it('14 · keeps its ten when it can\'t win the trick: an opponent\'s ace leads', function () {
    $state = proAct(proBazar(['AS', 'JH', '9H', 'AH', '7D', '8D', '7C', '8C'], [1 => ['TS', '7S', 'QD', 'JD', 'KC', 'QC', 'JC', '9C']]), [
        ['bid', ['value' => 8, 'suit' => 'H']], ['pass', []], ['pass', []], ['pass', []], 'AS',
    ]);

    // A sampled look-ahead: asked with five different random seeds, it keeps the ten nearly every time.
    $kept = count(array_filter(range(1, 5), fn (int $seed) => proChoice($state, $seed)->payload['card'] === '7S'));

    expect($state['to_act'])->toBe(1)->and($kept)->toBeGreaterThanOrEqual(4);
});

it('12 · keeps the master trump for the last trick and wins the seventh with the small one', function () {
    // Hearts trump. Six tricks are gone; on the seventh seat 0 led 8D, seat 1 (partner) 9D, seat 2 7D. The
    // bot (seat 3) holds only JH and 7H; 9H is still out, the only other trump.
    $history = [
        [[0, 'AS'], [1, 'KS'], [2, 'QS'], [3, 'JS']], [[0, 'AC'], [1, 'KC'], [2, 'QC'], [3, 'JC']],
        [[0, 'TS'], [1, '9S'], [2, '8S'], [3, '7S']], [[0, 'TC'], [1, '9C'], [2, '7C'], [3, 'AH']],
        [[3, 'TH'], [0, 'KH'], [1, 'QH'], [2, '8H']], [[3, 'AD'], [0, 'TD'], [1, 'QD'], [2, 'JD']],
    ];
    $state = proEndgame([0 => ['9H'], 1 => ['KD'], 2 => ['8C'], 3 => ['JH', '7H']], $history, [[0, '8D'], [1, '9D'], [2, '7D']], 'H', 1, 3);

    expect(proChoice($state)->payload['card'])->toBe('7H');
});

it('15 · with two cards left draws the last trump before cashing its ace, after an opponent showed no diamonds', function () {
    // Hearts trump. Seat 0 trumped a diamond earlier (no diamonds left), and 9H is still out.
    $history = [
        [[3, 'KD'], [0, '8H'], [1, '7D'], [2, '8D']], [[0, 'AS'], [1, 'KS'], [2, 'QS'], [3, 'JS']],
        [[0, 'AC'], [1, 'KC'], [2, 'QC'], [3, 'JC']], [[0, 'TS'], [1, '9S'], [2, '8S'], [3, '7S']],
        [[0, 'TC'], [1, '9C'], [2, '7C'], [3, 'AH']], [[3, 'TH'], [0, 'KH'], [1, 'QH'], [2, '7H']],
    ];
    $state = proEndgame([0 => ['9H', '8C'], 1 => ['QD', 'JD'], 2 => ['TD', '9D'], 3 => ['AD', 'JH']], $history, [], 'H', 1, 3);
    $memory = new CardMemory((new BlotEngine)->viewFor($state, 3));

    expect($memory->voids[0])->toHaveKey('D')
        ->and(proChoice($state)->payload['card'])->toBe('JH');
});

// ── Card memory and inference ────────────────────────────────────────────────────────────────────────────

it('9 · remembers who has no cards of a suit, and never deals them one', function () {
    $history = [[[3, 'KD'], [0, '8H'], [1, '7D'], [2, '8D']]];
    $state = proEndgame(proFill([0 => ['9H', '8C', 'AS', 'TS', 'KS', 'QS', 'JS'], 3 => ['AD', 'JH', 'AC', 'TC', 'KC', 'QC', 'JC']]), $history, [], 'H', 1, 3);
    $state['hands'] = array_map(fn (array $h) => array_values(array_diff($h, ['KD', '8H', '7D', '8D'])), $state['hands']);
    $view = (new BlotEngine)->viewFor($state, 3);
    $memory = new CardMemory($view);
    $sampler = new DealSampler;

    foreach (range(1, 60) as $seed) {
        $hands = $sampler->deal($memory, $view, new SeededRandom($seed));
        expect(array_filter($hands[0], fn (string $c) => $c[1] === 'D'))->toBe([]);
    }
});

it('reads a discard over an opponent\'s trump as "no higher trump", not "no trumps"', function () {
    // Hearts are trumps. Seat 3 leads a spade, seat 0 trumps with the 9, seat 1 (no spades) discards a diamond.
    $history = [[[3, 'KS'], [0, '9H'], [1, '7D'], [2, '8S']]];
    $state = proEndgame(proFill([0 => ['9H', 'AS', 'TS', 'QS', 'JS', 'AC', 'TC'], 1 => ['7D', '8H', '7H', 'KC', 'QC', 'JC', '9C']]), $history, [], 'H', 0, 1);
    $state['hands'] = array_map(fn (array $h) => array_values(array_diff($h, ['KS', '9H', '7D', '8S'])), $state['hands']);
    $memory = new CardMemory((new BlotEngine)->viewFor($state, 3));

    expect($memory->mayHold(1, '7H'))->toBeTrue()          // may still hold lower trumps
        ->and($memory->mayHold(1, 'JH'))->toBeFalse()      // but not the jack, which beats the nine
        ->and($memory->mayHold(1, 'AH'))->toBeTrue();      // the ace is below the nine
});

it('10 · knows the top trumps are gone: the ace is master now and nobody holds the jack or nine', function () {
    $history = [[[0, 'JH'], [1, '9H'], [2, '7H'], [3, '8H']]];
    $state = proEndgame(proFill([3 => ['AH', 'TH', 'AS', 'TS', 'KS', 'QS', 'JS']]), $history, [], 'H', 1, 1);
    $state['hands'] = array_map(fn (array $h) => array_values(array_diff($h, ['JH', '9H', '7H', '8H'])), $state['hands']);
    $view = (new BlotEngine)->viewFor($state, 3);
    $memory = new CardMemory($view);

    expect($memory->masterTrump())->toBe('AH')
        ->and($memory->mayHold(0, 'JH'))->toBeFalse()->and($memory->mayHold(1, '9H'))->toBeFalse();
    foreach (range(1, 30) as $seed) {
        $dealt = array_merge(...(new DealSampler)->deal($memory, $view, new SeededRandom($seed)));
        expect($dealt)->not->toContain('JH')->not->toContain('9H');
    }
});

it('16 · deals only what fits everything known, in every position of real deals (Hall\'s condition, no relaxing)', function () {
    $engine = new BlotEngine;
    $bot = new ClassicBlotBot;
    $sampler = new DealSampler;
    $checked = 0;
    foreach (range(1, 6) as $game) {
        $random = new SeededRandom($game * 17);
        $state = $engine->resume($engine->initialState(new GameSetup(4), $random), $random)->state;
        while ($state['last_hand'] === null) {
            if ($state['phase'] === 'dealing') {
                $state = $engine->resume($state, $random)->state;

                continue;
            }
            $seat = (int) $state['to_act'];
            $view = $engine->viewFor($state, $seat);
            if ($state['phase'] === 'playing') {
                $memory = new CardMemory($view);
                foreach (range(1, 4) as $n) {
                    $hands = $sampler->deal($memory, $view, new SeededRandom($checked * 7 + $n));
                    foreach (range(0, 3) as $s) {
                        expect($hands[$s])->toHaveCount((int) $view['hand_sizes'][$s]);
                        foreach ($hands[$s] as $card) {
                            expect($memory->mayHold($s, $card))->toBeTrue();
                        }
                    }
                }
                $checked++;
            }
            $state = $engine->apply($state, $seat, $bot->chooseAction($view, $engine->legalActions($state, $seat), BotDifficulty::Hard, $random), $random)->state;
        }
    }
    expect($checked)->toBeGreaterThan(100);
});

it('11 · weighs combinations by their priority: the opponents\' 50 beats its terz, so only theirs count', function () {
    $hands = proFill([0 => ['7S', '8S', '9S', 'KD', 'QC', 'JC', '7C', '8D'], 1 => ['7H', '8H', '9H', 'TH', 'AD', 'TD', 'AC', 'TC']]);
    $state = proEndgame($hands, [], [], 'D', 0, 0);
    $state['declared'] = [
        0 => [['kind' => 'terz', 'points' => 20, 'cards' => ['7S', '8S', '9S']]],
        1 => [['kind' => 'fifty', 'points' => 50, 'cards' => ['7H', '8H', '9H', 'TH']]],
        2 => [], 3 => [],
    ];
    $view = (new BlotEngine)->viewFor($state, 0);
    $simulator = new Simulator;
    $final = $simulator->finish($simulator->state($view, $hands, $hands), new SeededRandom(2));
    $counted = collect($final['last_hand']['declarations'])->where('counted', true)->pluck('seat')->unique()->values()->all();

    expect($counted)->toBe([1])->and($final['last_hand']['declaration_sai'])->toBe([0, 5]);
});

it('thinks like a person: a forced card quickly, a choice longer, the bazar and a Sur most deliberately', function () {
    $engine = new BlotEngine;
    $bot = new BlotBot;
    $time = function (array $state) use ($engine, $bot) {
        $seat = (int) $state['to_act'];
        $times = array_map(fn (int $seed) => $bot->thinkingMs($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), new SeededRandom($seed)), range(1, 20));

        return [min($times), max($times)];
    };
    $bazar = proBazar(['JH', '9H', 'AH', 'TH', 'KH', 'AS', 'AD', '7C']);
    $sur = proAct(proBazar(['JH', '9H', 'AH', 'TH', 'KH', 'QH', 'AS', 'AD']), [['bid', ['value' => 9, 'suit' => 'H']], ['pass', []], ['pass', []], ['contra', []]]);
    $forced = proEndgame([0 => ['9H'], 1 => ['KD'], 2 => ['8C'], 3 => ['JH', '7H']], [], [[0, '8D'], [1, '9D'], [2, '7D']], 'H', 1, 3);
    $forced['hands'][3] = ['JH'];
    $choice = proEndgame(proFill([0 => ['AS', 'KS', 'QD', 'JD', 'KC', 'QC', 'JC', '9C']]), [], [], 'H', 1, 0);

    [$forcedMin, $forcedMax] = $time($forced);
    [$choiceMin] = $time($choice);
    [$bazarMin] = $time($bazar);
    [$surMin] = $time($sur);
    expect($forcedMax)->toBeLessThan(1000)
        ->and($choiceMin)->toBeGreaterThan($forcedMax)
        ->and($bazarMin)->toBeGreaterThanOrEqual(1200)
        ->and($surMin)->toBeGreaterThanOrEqual(1800)
        // Not one fixed delay.
        ->and($forcedMin)->toBeLessThan($forcedMax);
});
