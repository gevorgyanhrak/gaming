<?php

use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Slots\House\Games\JungleSlot;
use Database\Seeders\HouseSlotSeeder;

/*
 * Jungle Slot (CC0 art, our maths): every reel has the same symbol counts, so each of the 20 lines sees the same
 * symbol law; the RTP is the expected pay of one line, summed exactly over all 7^5 symbol combinations.
 */

it('pays exactly 95.61387608 % (every symbol combination of a line, weighted by the reels)', function () {
    $laws = array_map(function (array $strip) {
        $law = array_fill_keys(JungleSlot::SYMBOLS, 0.0);
        foreach ($strip as $symbol) {
            $law[$symbol] += 1 / count($strip);
        }

        return $law;
    }, JungleSlot::reels());

    $rtp = 0.0;
    $hit = 0.0;
    $walk = function (int $reel, array $line, float $p) use (&$walk, $laws, &$rtp, &$hit) {
        if ($reel === JungleSlot::REELS) {
            $pay = JungleSlot::line($line)['pay'];
            $rtp += $p * $pay;
            $hit += $pay > 0 ? $p : 0;

            return;
        }
        foreach ($laws[$reel] as $symbol => $q) {
            if ($q > 0) {
                $walk($reel + 1, [...$line, $symbol], $p * $q);
            }
        }
    };
    $walk(0, [], 1.0);

    // A line bet's expected pay in line bets is the game's RTP (20 lines, each the same law, 20 line bets staked).
    expect(round($rtp, 10))->toBe(0.9561387608)
        ->and($hit)->toBeGreaterThan(0.05);
});

it('lets the chest stand in for any symbol and pay on its own when that is worth more', function () {
    expect(JungleSlot::line(['WILD', 'PINEAPPLE', 'PINEAPPLE', 'K', 'K']))->toBe(['symbol' => 'PINEAPPLE', 'count' => 3, 'pay' => 25])
        ->and(JungleSlot::line(['K', 'WILD', 'K', 'WILD', 'K']))->toBe(['symbol' => 'K', 'count' => 5, 'pay' => 75])
        ->and(JungleSlot::line(['WILD', 'WILD', 'WILD', 'K', 'BANANA']))->toBe(['symbol' => 'WILD', 'count' => 3, 'pay' => 50])
        ->and(JungleSlot::line(['WILD', 'WILD', 'WILD', 'WILD', 'WILD']))->toBe(['symbol' => 'WILD', 'count' => 5, 'pay' => 2500])
        ->and(JungleSlot::line(['K', 'K', 'BANANA', 'K', 'K'])['pay'])->toBe(0);
});

it('scores a dealt screen on all 20 lines, in total bets, with the winning positions', function () {
    $grid = [
        ['PINEAPPLE', 'K', 'BANANA'],
        ['PINEAPPLE', 'WILD', 'BANANA'],
        ['PINEAPPLE', 'K', 'APRICOT'],
        ['COCONUT', 'K', 'BANANA'],
        ['MUSHROOM', 'COCONUT', 'APRICOT'],
    ];
    $spin = JungleSlot::evaluate($grid);
    $top = collect($spin['lineWins'])->firstWhere('line', 1); // line 2: the top row
    expect($top)->toMatchArray(['symbol' => 'PINEAPPLE', 'count' => 3, 'amount' => 25 / 20, 'positions' => [[0, 0], [1, 0], [2, 0]]])
        ->and(collect($spin['lineWins'])->firstWhere('line', 0))->toMatchArray(['symbol' => 'K', 'count' => 4, 'amount' => 20 / 20])
        ->and($spin['totalWin'])->toEqual(array_sum(array_column($spin['lineWins'], 'amount')));
});

it('deals from the reels with the injected randomness', function () {
    $round = (new JungleSlot)->play(fn (int $n) => 0);
    expect($round['round']['grid'][0])->toBe(['PINEAPPLE', 'MUSHROOM', 'BANANA'])
        ->and($round['total_win'])->toEqual($round['round']['totalWin']);
});

it('is dealt by our server through the slot wallet, and lists its own credit', function () {
    $this->seed(HouseSlotSeeder::class);
    $user = User::factory()->create();
    app(FishkaWallets::class)->open($user); // 5,000 welcome Fishkas

    $this->getJson('/api/v1/slots')->assertOk()
        ->assertJsonFragment(['slug' => 'jungle-slot', 'play_path' => '/play/jungle-slot/'])
        ->assertJsonFragment(['credit' => (new JungleSlot)->credit()]);

    $round = $this->actingAs($user)->postJson('/api/v1/slots/jungle-slot/spin', ['bet' => 20, 'key' => 'jungle-0001'])
        ->assertOk()->json('data');
    expect($round['bet'])->toBe(20)
        ->and($round['round']['grid'])->toHaveCount(5)
        ->and($round['win'])->toBe((int) floor(20 * $round['round']['totalWin'] + 1e-9))
        ->and($round['balance'])->toBe(5000 - 20 + $round['win']);
});
