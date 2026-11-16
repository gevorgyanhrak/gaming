<?php

use App\Enums\FishkaTransactionType as Type;
use App\Models\FishkaTransaction;
use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Models\SlotSpin;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Slots\House\Games\ClassicThreeReels;
use App\Platform\Slots\House\Games\NitroHot;
use App\Platform\Slots\House\Games\ScorchingHot;
use App\Platform\Slots\House\Games\StellarSevens;
use App\Platform\Slots\House\Games\TropicEscape;
use App\Platform\Slots\House\HouseSlots;
use Database\Seeders\HouseSlotSeeder;
use Tests\Fixtures\Slots\FixedHouseSlot;

beforeEach(function () {
    app()->tag([FixedHouseSlot::class], 'house-slots');
    FixedHouseSlot::$win = 0.0;
    FixedHouseSlot::$plays = 0;
    $this->provider = SlotProvider::query()->create(['slug' => HouseSlots::PROVIDER, 'name' => 'APEXSLOTS', 'is_enabled' => true]);
    $this->game = SlotGame::query()->create([
        'slot_provider_id' => $this->provider->id, 'provider_game_id' => 'fixed', 'slug' => 'fixed',
        'name' => ['en' => 'Fixed'], 'min_bet' => 10, 'max_bet' => 1000, 'max_multiplier' => 100, 'is_enabled' => true,
    ]);
    $this->user = User::factory()->create();
    app(FishkaWallets::class)->open($this->user); // 5,000 welcome Fishkas
});

function houseBalance(User $user): int
{
    return app(FishkaWallets::class)->wallet($user)->balance;
}

it('opens a house slot with the balance and the bets within the game\'s limits', function () {
    $this->actingAs($this->user)->getJson('/api/v1/slots/fixed/play')->assertOk()
        ->assertJsonPath('data.balance', 5000)
        ->assertJsonPath('data.bets', [10, 20, 50, 100, 200, 500, 1000]);
});

it('deals a round on the server: takes the bet, pays the win, records the spin', function () {
    FixedHouseSlot::$win = 2.5;
    $this->actingAs($this->user)->postJson('/api/v1/slots/fixed/spin', ['bet' => 100, 'key' => 'spin-0001'])->assertOk()
        ->assertJsonPath('data.bet', 100)->assertJsonPath('data.win', 250)->assertJsonPath('data.balance', 5150)
        ->assertJsonPath('data.replay', false)->assertJsonPath('data.round.totalWin', 2.5);

    expect(houseBalance($this->user))->toBe(5150)
        ->and(FishkaTransaction::query()->where('type', Type::SlotBet)->sum('amount'))->toEqual(-100)
        ->and(FishkaTransaction::query()->where('type', Type::SlotWin)->sum('amount'))->toEqual(250)
        ->and(SlotSpin::query()->count())->toBe(1);
});

it('answers a retried spin with the round already dealt and charges it once', function () {
    FixedHouseSlot::$win = 0.0;
    $first = $this->actingAs($this->user)->postJson('/api/v1/slots/fixed/spin', ['bet' => 50, 'key' => 'spin-0002'])->assertOk();
    $again = $this->actingAs($this->user)->postJson('/api/v1/slots/fixed/spin', ['bet' => 50, 'key' => 'spin-0002'])->assertOk();

    expect($again->json('data.replay'))->toBeTrue()
        ->and($again->json('data.round_id'))->toBe($first->json('data.round_id'))
        ->and(FixedHouseSlot::$plays)->toBe(1)
        ->and(houseBalance($this->user))->toBe(4950);
});

it('refuses a reused key with another bet, a bet the game doesn\'t offer, and a bet the balance can\'t cover', function () {
    $this->actingAs($this->user)->postJson('/api/v1/slots/fixed/spin', ['bet' => 50, 'key' => 'spin-0003'])->assertOk();
    $this->actingAs($this->user)->postJson('/api/v1/slots/fixed/spin', ['bet' => 100, 'key' => 'spin-0003'])
        ->assertUnprocessable()->assertJsonPath('code', 'slot_key_conflict');
    $this->actingAs($this->user)->postJson('/api/v1/slots/fixed/spin', ['bet' => 15, 'key' => 'spin-0004'])
        ->assertUnprocessable()->assertJsonPath('code', 'slot_bet_limits');

    $this->game->update(['max_bet' => 10000]);
    $this->actingAs($this->user)->postJson('/api/v1/slots/fixed/spin', ['bet' => 10000, 'key' => 'spin-0005'])
        ->assertUnprocessable()->assertJsonPath('code', 'insufficient_fishkas')->assertJsonPath('balance', 4950);

    expect(houseBalance($this->user))->toBe(4950)->and(SlotSpin::query()->count())->toBe(1);
});

it('never pays more than the game allows, and then takes no bet either', function () {
    FixedHouseSlot::$win = 500.0; // above the game's max_multiplier of 100
    $this->actingAs($this->user)->postJson('/api/v1/slots/fixed/spin', ['bet' => 10, 'key' => 'spin-0006'])
        ->assertUnprocessable()->assertJsonPath('code', 'slot_win_over_limit');

    expect(houseBalance($this->user))->toBe(5000)->and(SlotSpin::query()->count())->toBe(0);
});

it('deals nothing for guests, a game that is off, or a game the code doesn\'t have', function () {
    $this->postJson('/api/v1/slots/fixed/spin', ['bet' => 10, 'key' => 'spin-0007'])->assertUnauthorized();

    $this->game->update(['is_enabled' => false]);
    $this->actingAs($this->user)->postJson('/api/v1/slots/fixed/spin', ['bet' => 10, 'key' => 'spin-0008'])
        ->assertUnprocessable()->assertJsonPath('code', 'slot_game_off');

    $this->game->update(['is_enabled' => true, 'provider_game_id' => 'missing']);
    $this->actingAs($this->user)->getJson('/api/v1/slots/fixed/play')->assertUnprocessable()->assertJsonPath('code', 'slot_game_off');
    expect(houseBalance($this->user))->toBe(5000);
});

it('seeds the house games once and keeps an administrator\'s changes', function () {
    $this->seed(HouseSlotSeeder::class);
    $game = SlotGame::query()->where('slug', 'stellar-sevens')->firstOrFail();
    expect($game->max_multiplier)->toBe(300)->and($game->is_enabled)->toBeTrue();

    $game->update(['is_enabled' => false, 'max_bet' => 200]);
    $this->seed(HouseSlotSeeder::class);
    expect($game->refresh()->is_enabled)->toBeFalse()->and($game->max_bet)->toBe(200);
    $this->getJson('/api/v1/slots')->assertOk()->assertJsonMissing(['slug' => 'stellar-sevens']);
});

it('deals each classic 3x3 slot at its published RTP, proven over every stop combination', function (ClassicThreeReels $game, float $rtp) {
    expect(round($game->exactRtp(), 6))->toBe($rtp);
})->with([
    'Stellar Sevens' => [new StellarSevens, 95.504233],
    'Nitro Hot' => [new NitroHot, 95.658244],
]);

it('doubles every Nitro Hot line on a full screen of one fruit, but not of sevens', function () {
    $cherries = (new NitroHot)->evaluate(array_fill(0, 3, array_fill(0, 3, 'CHERRY')));
    $sevens = (new NitroHot)->evaluate(array_fill(0, 3, array_fill(0, 3, 'SEVEN')));
    expect($cherries['fillMult'])->toBe(2)->and($cherries['totalWin'])->toBe(80.0)
        ->and($sevens['fillMult'])->toBe(1)->and($sevens['totalWin'])->toBe(750.0);
});

/**
 * The exact return of a lines-and-scatter slot with independent reels and no wild: a line's cell on a reel is
 * uniform over that strip, so each line's expected pay follows from the symbol counts; the scatter's from each
 * reel's count of scatters in its 3-row window, convolved over the reels.
 *
 * @param  list<list<string>>  $reels
 * @param  array<string, array<int, int>>  $linePay  per line bet, run of 1…n (a line is LINES ⁻¹ of the bet, LINES lines)
 * @param  list<int|float>  $scatPay  total bets for 1…n scatters
 */
function linesAndScatterRtp(array $reels, array $linePay, string $scatter, array $scatPay): float
{
    $p = array_map(fn (array $strip) => array_map(fn (int $n) => $n / count($strip), array_count_values($strip)), $reels);
    $line = 0.0;
    foreach ($linePay as $symbol => $pays) {
        $run = 1.0;
        foreach ($reels as $reel => $strip) {
            $run *= $p[$reel][$symbol] ?? 0.0;
            $next = $reel + 1 < count($reels) ? 1 - ($p[$reel + 1][$symbol] ?? 0.0) : 1.0;
            $line += $run * $next * $pays[$reel];
        }
    }

    $dist = [1.0];
    foreach ($reels as $strip) {
        $counts = [];
        for ($stop = 0; $stop < count($strip); $stop++) {
            $n = 0;
            for ($row = 0; $row < 3; $row++) {
                $n += $strip[($stop + $row) % count($strip)] === $scatter ? 1 : 0;
            }
            $counts[$n] = ($counts[$n] ?? 0) + 1 / count($strip);
        }
        $next = [];
        foreach ($dist as $have => $pHave) {
            foreach ($counts as $add => $pAdd) {
                $next[$have + $add] = ($next[$have + $add] ?? 0) + $pHave * $pAdd;
            }
        }
        $dist = $next;
    }
    $scat = 0.0;
    foreach ($dist as $n => $pN) {
        $scat += $n > 0 ? $pN * ($scatPay[min($n, count($scatPay)) - 1] ?? 0) : 0;
    }

    return ($line + $scat) * 100;
}

it('deals Scorching Hot at its published RTP of 95.655629 %, and scores runs and stars as the original', function () {
    expect(round(linesAndScatterRtp(ScorchingHot::reels(), ScorchingHot::LINE_PAY, 'STAR', ScorchingHot::SCAT_PAY), 6))->toBe(95.655629);

    $grid = [['CHERRY', 'STAR', 'SEVEN'], ['CHERRY', 'PLUM', 'STAR'], ['LEMON', 'STAR', 'PLUM'], ['LEMON', 'MELON', 'PLUM'], ['ORANGE', 'MELON', 'PLUM']];
    $spin = ScorchingHot::evaluate($grid);
    expect($spin['lineWins'])->toHaveCount(1)
        ->and($spin['lineWins'][0])->toMatchArray(['line' => 1, 'symbol' => 'CHERRY', 'count' => 2, 'amount' => 1])
        ->and($spin['scatterWin'])->toMatchArray(['count' => 3, 'amount' => 2])
        ->and($spin['totalWin'])->toBe(3.0);
});

/**
 * Exact expectations of one board of independent reels: the pay of one line (every line has the same law: a cell
 * is uniform over its strip), and the law of the scatter count (each reel's 3-row window, convolved).
 *
 * @param  list<list<string>>  $reels
 * @param  Closure(list<string>): ?array  $lineWin  [symbol, count, mult, pay per line bet] or null
 * @return array{line: float, scatters: array<int, float>}
 */
function boardLaw(array $reels, Closure $lineWin, string $scatter): array
{
    $p = array_map(fn (array $strip) => array_map(fn (int $n) => $n / count($strip), array_count_values($strip)), $reels);
    $line = 0.0;
    $walk = function (int $reel, array $symbols, float $prob) use (&$walk, &$line, $p, $lineWin, $reels) {
        if ($reel === count($reels)) {
            $line += $prob * ($lineWin($symbols)[3] ?? 0);

            return;
        }
        foreach ($p[$reel] as $symbol => $q) {
            $walk($reel + 1, [...$symbols, (string) $symbol], $prob * $q);
        }
    };
    $walk(0, [], 1.0);

    $dist = [1.0];
    foreach ($reels as $strip) {
        $counts = [];
        for ($stop = 0; $stop < count($strip); $stop++) {
            $n = 0;
            for ($row = 0; $row < 3; $row++) {
                $n += $strip[($stop + $row) % count($strip)] === $scatter ? 1 : 0;
            }
            $counts[$n] = ($counts[$n] ?? 0) + 1 / count($strip);
        }
        $next = [];
        foreach ($dist as $have => $pHave) {
            foreach ($counts as $add => $pAdd) {
                $next[$have + $add] = ($next[$have + $add] ?? 0) + $pHave * $pAdd;
            }
        }
        $dist = $next;
    }

    return ['line' => $line, 'scatters' => $dist];
}

it('deals Tropic Escape at its published RTP of 95.364527 % (base game, scatters and free games, exactly)', function () {
    $scat = fn (array $law, array $pays) => array_sum(array_map(fn ($n, $pn) => $n > 0 ? $pn * $pays[min($n, 5) - 1] : 0, array_keys($law), $law));
    $trigger = fn (array $law) => array_sum(array_filter($law, fn ($pn, $n) => $n >= 3, ARRAY_FILTER_USE_BOTH));
    $lineWin = TropicEscape::lineWin(...);

    $base = boardLaw(TropicEscape::reels(TropicEscape::BASE), $lineWin, 'SUITCASE');
    $bonus = boardLaw(TropicEscape::reels(TropicEscape::BONUS), $lineWin, 'SUITCASE');
    $perFreeSpin = $bonus['line'] + $scat($bonus['scatters'], TropicEscape::SCAT_PAY);
    $spinsPerTrigger = 45 / (1 - 45 * $trigger($bonus['scatters'])); // Wald: every spin may add 45 more
    $rtp = $base['line'] + $scat($base['scatters'], TropicEscape::SCAT_PAY) + $trigger($base['scatters']) * $spinsPerTrigger * $perFreeSpin;

    expect(round($rtp * 100, 6))->toBe(95.364527)
        ->and(round($spinsPerTrigger * $perFreeSpin / 45 * 100, 6))->toBe(210.461503); // the README's figure is per free spin
});

it('scores a Tropic Escape line as the original: wilds double a run, a banana run pays when it is worth more', function () {
    expect(TropicEscape::lineWin(['BANANA', 'MELON', 'MELON', 'ACE', 'ACE']))->toBe(['MELON', 3, 2, 60])
        ->and(TropicEscape::lineWin(['BANANA', 'BANANA', 'BANANA', 'NINE', 'ACE']))->toBe(['BANANA', 3, 1, 250])
        ->and(TropicEscape::lineWin(['ACE', 'KING', 'ACE', 'ACE', 'ACE']))->toBeNull();

    $dealt = (new TropicEscape)->play(fn (int $n) => 0);
    expect($dealt['round'])->toHaveKeys(['baseSpin', 'totalWin'])->and($dealt['round']['baseSpin'])->toHaveKey('freeSpinsWon');
});

it('plays a Stellar Sevens round in the shape its client reads', function () {
    $dealt = (new StellarSevens)->play(fn (int $n) => 0);
    expect($dealt['round']['baseSpin']['grid'])->toBe([['SEVEN', 'SEVEN', 'SEVEN'], ['SEVEN', 'SEVEN', 'SEVEN'], ['SEVEN', 'SEVEN', 'SEVEN']])
        ->and($dealt['total_win'])->toBe(300.0)
        ->and($dealt['round']['baseSpin']['lineWins'][0])->toMatchArray(['line' => 0, 'symbol' => 'SEVEN', 'count' => 3, 'amount' => 60, 'positions' => [[0, 1], [1, 1], [2, 1]]]);
});
