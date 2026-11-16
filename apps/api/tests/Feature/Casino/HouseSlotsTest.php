<?php

use App\Enums\FishkaTransactionType as Type;
use App\Models\FishkaTransaction;
use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Models\SlotSpin;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Slots\House\Games\StellarSevens;
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

it('deals Stellar Sevens at its published RTP of 95.504233 %, proven over every stop combination', function () {
    $length = count(StellarSevens::STRIP);
    $symbols = array_map(fn (int $n) => StellarSevens::SYMBOLS[$n - 1], StellarSevens::STRIP);
    $total = 0.0;
    $best = 0.0;
    for ($a = 0; $a < $length; $a++) {
        for ($b = 0; $b < $length; $b++) {
            for ($c = 0; $c < $length; $c++) {
                $grid = [];
                foreach ([$a, $b, $c] as $reel => $stop) {
                    for ($row = 0; $row < 3; $row++) {
                        $grid[$reel][$row] = $symbols[($stop + $row) % $length];
                    }
                }
                $win = StellarSevens::evaluate($grid)['totalWin'];
                $total += $win;
                $best = max($best, $win);
            }
        }
    }

    expect(round($total / $length ** 3 * 100, 6))->toBe(95.504233)->and($best)->toBe(300.0);
});

it('plays a Stellar Sevens round in the shape its client reads', function () {
    $dealt = (new StellarSevens)->play(fn (int $n) => 0);
    expect($dealt['round']['baseSpin']['grid'])->toBe([['SEVEN', 'SEVEN', 'SEVEN'], ['SEVEN', 'SEVEN', 'SEVEN'], ['SEVEN', 'SEVEN', 'SEVEN']])
        ->and($dealt['total_win'])->toBe(300.0)
        ->and($dealt['round']['baseSpin']['lineWins'][0])->toMatchArray(['line' => 0, 'symbol' => 'SEVEN', 'count' => 3, 'amount' => 60, 'positions' => [[0, 1], [1, 1], [2, 1]]]);
});
