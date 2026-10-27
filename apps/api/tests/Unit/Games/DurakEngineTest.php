<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\SecureRandom;
use App\Games\Durak\Bot\DurakBot;
use App\Games\Durak\Engine\DurakEngine;

/**
 * @param  list<string>  $attackerHand
 * @param  list<string>  $defenderHand
 * @param  list<string>  $deck  The last card is the face-up trump card.
 */
function durak(array $attackerHand, array $defenderHand, array $deck = [], string $trump = 'S'): array
{
    return [
        'deck' => $deck,
        'trump' => $trump,
        'trump_card' => $deck === [] ? null : $deck[count($deck) - 1],
        'hands' => [$attackerHand, $defenderHand],
        'table' => [],
        'attacker' => 0,
        'defender' => 1,
        'taking' => false,
        'limit' => min(6, count($defenderHand)),
        'discarded' => 0,
        'result' => null,
    ];
}

function durakPlay(array $state, string $type, ?string $card = null): array
{
    $seat = (new DurakEngine)->seatsToAct($state)[0];

    return (new DurakEngine)->apply($state, $seat, new Action($type, $card === null ? [] : ['card' => $card]), new SecureRandom)->state;
}

/** @return list<string> e.g. ['defend:9H', 'take'] */
function durakOptions(array $state): array
{
    $engine = new DurakEngine;

    return array_map(fn (Action $a) => $a->type.(isset($a->payload['card']) ? ':'.$a->payload['card'] : ''), $engine->legalActions($state, $engine->seatsToAct($state)[0]));
}

it('deals six cards each from a shuffled 36-card deck and lets the lowest trump lead', function () {
    $engine = new DurakEngine;

    foreach (range(1, 20) as $deal) {
        $state = $engine->initialState(new GameSetup(2), new SecureRandom);
        $all = [...$state['deck'], ...$state['hands'][0], ...$state['hands'][1]];

        expect($state['hands'][0])->toHaveCount(6)
            ->and($state['deck'])->toHaveCount(24)
            ->and(array_unique($all))->toHaveCount(36)
            ->and($state['trump'])->toBe($state['deck'][23][1]);

        $lowest = function (array $hand) use ($state) {
            $ranks = array_map(fn ($c) => strpos('6789TJQKA', $c[0]), array_filter($hand, fn ($c) => $c[1] === $state['trump']));

            return $ranks === [] ? 99 : min($ranks);
        };
        $l0 = $lowest($state['hands'][0]);
        $l1 = $lowest($state['hands'][1]);
        if ($l0 !== $l1) {
            expect($state['attacker'])->toBe($l0 < $l1 ? 0 : 1);
        }
    }
});

it('shows each player only their own hand', function () {
    $engine = new DurakEngine;
    $state = $engine->initialState(new GameSetup(2), new SecureRandom);
    $json = json_encode($engine->viewFor($state, 0));
    $hidden = array_diff([...$state['hands'][1], ...array_slice($state['deck'], 0, -1)], [$state['trump_card']]);

    foreach ($hidden as $card) {
        expect($json)->not->toContain('"'.$card.'"');
    }
    expect($engine->viewFor($state, 0)['hand'])->toBe($state['hands'][0])
        ->and($engine->viewFor($state, 0)['hand_counts'])->toBe([6, 6])
        ->and($engine->viewFor($state, null)['hand'])->toBe([]);
});

it('only lets a higher card of the suit or a trump beat an attack', function () {
    $state = durakPlay(durak(['9H', '7C'], ['6H', 'TH', '6S', 'AC', 'KD'], ['7D', '8D']), 'attack', '9H');

    expect(durakOptions($state))->toBe(['defend:TH', 'defend:6S', 'take']);
});

it('lets a trump be beaten only by a higher trump', function () {
    $state = durakPlay(durak(['8S', '7C'], ['7S', 'AS', 'AH'], ['7D', '8D']), 'attack', '8S');

    expect(durakOptions($state))->toBe(['defend:AS', 'take']);
});

it('allows throwing in ranks on the table, up to the defender’s hand', function () {
    $state = durakPlay(durak(['9H', '9C', 'TD', '6C'], ['TH', 'JH'], ['7D', '8D']), 'attack', '9H');
    $state = durakPlay($state, 'defend', 'TH');

    // 9 and 10 are on the table; 6C isn't playable.
    expect(durakOptions($state))->toBe(['attack:9C', 'attack:TD', 'done']);

    $state = durakPlay(durakPlay($state, 'attack', '9C'), 'take');
    // The defender held two cards when the bout began: no more throwing in, so they pick up.
    expect($state['hands'][1])->toEqualCanonicalizing(['JH', '9H', 'TH', '9C'])
        ->and($state['table'])->toBe([])
        ->and($state['attacker'])->toBe(0);
});

it('discards a beaten bout, refills attacker first and passes the attack', function () {
    $state = durakPlay(durak(['9H', '6C'], ['TH', 'JC', 'QC'], ['7D', '8D', 'AS']), 'attack', '9H');
    $state = durakPlay($state, 'defend', 'TH');

    // 6C doesn't match, so the bout ends by itself.
    expect($state['table'])->toBe([])
        ->and($state['discarded'])->toBe(2)
        ->and($state['hands'][0])->toBe(['6C', '7D', '8D', 'AS'])
        ->and($state['hands'][1])->toBe(['JC', 'QC'])
        ->and($state['attacker'])->toBe(1)
        ->and($state['defender'])->toBe(0);
});

it('lets the attacker keep throwing in after the defender takes', function () {
    $state = durakPlay(durak(['9H', '9C', '6C'], ['TH', 'JC', 'QC'], ['7D', '8D']), 'attack', '9H');
    $state = durakPlay($state, 'take');

    expect((new DurakEngine)->seatsToAct($state))->toBe([0])
        ->and(durakOptions($state))->toBe(['attack:9C', 'done']);

    $state = durakPlay($state, 'done');
    expect($state['hands'][1])->toEqualCanonicalizing(['TH', 'JC', 'QC', '9H']);
});

it('ends with the player who still holds cards as the durak', function () {
    $state = durakPlay(durak(['9H'], ['TH', '6C']), 'attack', '9H');
    $state = durakPlay($state, 'defend', 'TH');

    expect((new DurakEngine)->outcome($state)?->winners)->toBe([0])
        ->and((new DurakEngine)->outcome($state)?->reason)->toBe('durak');
});

it('is a draw when both players run out together', function () {
    $state = durakPlay(durak(['9H'], ['TH']), 'attack', '9H');
    $state = durakPlay($state, 'defend', 'TH');

    expect((new DurakEngine)->outcome($state)?->isDraw())->toBeTrue();
});

it('rejects cards not in hand, losing defences and moves out of turn', function () {
    $engine = new DurakEngine;
    $state = durak(['9H', '6C'], ['8H', 'JC'], ['7D', '8D']);

    expect(fn () => durakPlay($state, 'attack', 'AS'))->toThrow(IllegalAction::class)
        ->and(fn () => $engine->apply($state, 1, new Action('attack', ['card' => '8H']), new SecureRandom))->toThrow(IllegalAction::class)
        ->and(fn () => durakPlay(durakPlay($state, 'attack', '9H'), 'defend', '8H'))->toThrow(IllegalAction::class);
});

it('keeps all 36 cards in play through random games', function () {
    $engine = new DurakEngine;
    $random = new SecureRandom;

    foreach (range(1, 40) as $game) {
        $state = $engine->initialState(new GameSetup(2), $random);
        for ($i = 0; $i < 1000 && $engine->outcome($state) === null; $i++) {
            $seat = $engine->seatsToAct($state)[0];
            $legal = $engine->legalActions($state, $seat);
            expect($legal)->not->toBeEmpty();
            $state = $engine->apply($state, $seat, $legal[$random->int(0, count($legal) - 1)], $random)->state;

            $onTable = array_sum(array_map(fn ($p) => $p['defense'] === null ? 1 : 2, $state['table']));
            expect(count($state['deck']) + count($state['hands'][0]) + count($state['hands'][1]) + $onTable + $state['discarded'])->toBe(36);
        }
        expect($engine->outcome($state))->not->toBeNull();
    }
});

it('has bots play legal actions and the medium bot beat random play', function () {
    $engine = new DurakEngine;
    $bot = new DurakBot;
    $random = new SecureRandom;
    $score = ['medium' => 0, 'easy' => 0];

    foreach (range(1, 30) as $game) {
        $mediumSeat = $game % 2;
        $state = $engine->initialState(new GameSetup(2), $random);
        for ($i = 0; $i < 1000 && $engine->outcome($state) === null; $i++) {
            $seat = $engine->seatsToAct($state)[0];
            $legal = $engine->legalActions($state, $seat);
            $level = $seat === $mediumSeat ? BotDifficulty::Medium : BotDifficulty::Easy;
            $choice = $bot->chooseAction($engine->viewFor($state, $seat), $legal, $level, $random);
            expect(collect($legal)->contains(fn (Action $a) => $a->equals($choice)))->toBeTrue();
            $state = $engine->apply($state, $seat, $choice, $random)->state;
        }
        $winners = $engine->outcome($state)?->winners ?? [];
        if ($winners !== []) {
            $score[$winners === [$mediumSeat] ? 'medium' : 'easy']++;
        }
    }

    expect($score['medium'])->toBeGreaterThan($score['easy'] * 2);
});

it('has the hard bot hold its own against medium', function () {
    $engine = new DurakEngine;
    $bot = new DurakBot;
    $random = new SecureRandom;
    $hardWins = 0;
    $decided = 0;

    foreach (range(1, 60) as $game) {
        $hardSeat = $game % 2;
        $state = $engine->initialState(new GameSetup(2), $random);
        for ($i = 0; $i < 1000 && $engine->outcome($state) === null; $i++) {
            $seat = $engine->seatsToAct($state)[0];
            $level = $seat === $hardSeat ? BotDifficulty::Hard : BotDifficulty::Medium;
            $state = $engine->apply($state, $seat, $bot->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), $level, $random), $random)->state;
        }
        $winners = $engine->outcome($state)?->winners ?? [];
        $decided += $winners === [] ? 0 : 1;
        $hardWins += $winners === [$hardSeat] ? 1 : 0;
    }

    expect($hardWins)->toBeGreaterThanOrEqual(intdiv($decided, 3));
});
