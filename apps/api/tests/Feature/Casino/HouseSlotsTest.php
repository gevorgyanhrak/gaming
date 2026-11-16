<?php

use App\Enums\FishkaTransactionType as Type;
use App\Models\FishkaTransaction;
use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Models\SlotSpin;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Slots\House\Games\ClassicThreeReels;
use App\Platform\Slots\House\Games\DiscoveryVoyage;
use App\Platform\Slots\House\Games\GoldenPapyrus;
use App\Platform\Slots\House\Games\HouseOfRa;
use App\Platform\Slots\House\Games\NitroHot;
use App\Platform\Slots\House\Games\PearlReef;
use App\Platform\Slots\House\Games\Samurai;
use App\Platform\Slots\House\Games\ScorchingHot;
use App\Platform\Slots\House\Games\StellarSevens;
use App\Platform\Slots\House\Games\TheDynasty;
use App\Platform\Slots\House\Games\TropicEscape;
use App\Platform\Slots\House\Games\WildLinesFreeGames;
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

it('deals each wild-lines slot at its published RTP (base game, scatters and free games, exactly)', function (string $game, float $rtp) {
    /** @var class-string<WildLinesFreeGames> $game */
    $expect = fn (array $law, array $values) => array_sum(array_map(fn ($n, $pn) => $n > 0 ? $pn * $values[min($n, 5) - 1] : 0, array_keys($law), $law));
    $base = boardLaw($game::reels($game::baseStrips()), $game::lineWin(...), $game::scatter());
    $bonus = boardLaw($game::reels($game::bonusStrips()), fn (array $line) => $game::lineWin($line, true), $game::scatter());

    $perFreeSpin = ($bonus['line'] + $expect($bonus['scatters'], $game::scatPay())) * $game::FREE_MULT;
    // Wald: a session's expected length is its first award over (1 − the spins each free spin adds on average).
    $spins = $expect($base['scatters'], $game::scatFreeSpins()) / (1 - $expect($bonus['scatters'], $game::scatFreeSpins()));
    $total = $base['line'] + $expect($base['scatters'], $game::scatPay()) + $spins * $perFreeSpin;

    expect(round($total * 100, 6))->toBe($rtp);
})->with([
    'Tropic Escape' => [TropicEscape::class, 95.364527],
    'Pearl Reef' => [PearlReef::class, 95.133189],
    'Discovery Voyage' => [DiscoveryVoyage::class, 95.022795],
]);

/**
 * The law of how many cells of one symbol a board shows (each reel's 3-row window, convolved).
 *
 * @param  list<list<string>>  $reels
 * @return array<int, float>
 */
function cellCountLaw(array $reels, string $symbol): array
{
    $dist = [1.0];
    foreach ($reels as $strip) {
        $counts = [];
        for ($stop = 0; $stop < count($strip); $stop++) {
            $n = 0;
            for ($row = 0; $row < 3; $row++) {
                $n += $strip[($stop + $row) % count($strip)] === $symbol ? 1 : 0;
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

    return $dist;
}

it('deals Golden Papyrus at its published RTP of 95.111967 %, averaging the nine expanding symbols, exactly', function () {
    $scatPay = fn (array $law) => array_sum(array_map(fn ($n, $p) => $n >= 3 ? $p * GoldenPapyrus::SCAT_PAY[min($n, 5) - 1] : 0, array_keys($law), $law));
    $trigger = fn (array $law) => array_sum(array_filter($law, fn ($p, $n) => $n >= 3, ARRAY_FILTER_USE_BOTH));
    $baseReels = GoldenPapyrus::reels(GoldenPapyrus::BASE);
    $bonusReels = GoldenPapyrus::reels(GoldenPapyrus::BONUS);

    $base = boardLaw($baseReels, fn (array $line) => ($w = GoldenPapyrus::lineWin($line, null)) ? [0, 0, 0, $w[2]] : null, 'PAPYRUS');
    $bonusScatters = cellCountLaw($bonusReels, 'PAPYRUS');
    $perFreeSpin = 0.0;
    foreach (array_slice(GoldenPapyrus::SYMBOLS, 0, 9) as $es) {
        $line = boardLaw($bonusReels, fn (array $l) => ($w = GoldenPapyrus::lineWin($l, $es)) ? [0, 0, 0, $w[2]] : null, 'PAPYRUS')['line'];
        $expand = array_sum(array_map(fn ($c, $p) => $c >= 2 ? $p * (GoldenPapyrus::LINE_PAY[$es][$c - 1] ?? 0) : 0, array_keys($law = cellCountLaw($bonusReels, $es)), $law));
        $perFreeSpin += ($line + $expand + $scatPay($bonusScatters)) / 9;
    }
    $spins = 10 / (1 - 10 * $trigger($bonusScatters));
    $rtp = $base['line'] + $scatPay($base['scatters']) + $trigger($base['scatters']) * $spins * $perFreeSpin;

    expect(round($rtp * 100, 6))->toBe(95.111967);
});

it('deals House of Ra at its published RTP of 95.182099 % (lines, scatters, best-way free games), exactly', function () {
    $scat = fn (array $law, array $values) => array_sum(array_map(fn ($n, $p) => $n > 0 ? $p * $values[min($n, 5) - 1] : 0, array_keys($law), $law));
    $base = boardLaw(HouseOfRa::reels(HouseOfRa::BASE), fn (array $l) => ($w = HouseOfRa::lineWin($l)) ? [0, 0, 0, $w[2]] : null, 'SUNDISC');
    $freeReels = HouseOfRa::reels(HouseOfRa::FREE);

    // Best way, by dynamic programming over the reels: a state is the symbols still running from reel 1 and the
    // best pay of the runs that already ended; each reel's law is the set of symbols its window shows.
    $states = [json_encode([array_keys(HouseOfRa::LINE_PAY), 0]) => 1.0];
    foreach ($freeReels as $x => $strip) {
        $sets = [];
        for ($stop = 0; $stop < count($strip); $stop++) {
            $window = array_unique([$strip[$stop % count($strip)], $strip[($stop + 1) % count($strip)], $strip[($stop + 2) % count($strip)]]);
            sort($window);
            $sets[json_encode($window)] = ($sets[json_encode($window)] ?? 0) + 1 / count($strip);
        }
        $next = [];
        foreach ($states as $key => $p) {
            [$alive, $dead] = json_decode($key, true);
            foreach ($sets as $setKey => $q) {
                $set = json_decode($setKey, true);
                $stay = array_values(array_intersect($alive, $set));
                $best = $dead;
                foreach (array_diff($alive, $set) as $gone) {
                    $best = max($best, $x > 0 ? HouseOfRa::LINE_PAY[$gone][$x - 1] : 0);
                }
                $k = json_encode([$stay, $best]);
                $next[$k] = ($next[$k] ?? 0) + $p * $q;
            }
        }
        $states = $next;
    }
    $way = 0.0;
    foreach ($states as $key => $p) {
        [$alive, $dead] = json_decode($key, true);
        $five = array_map(fn (string $symbol) => HouseOfRa::LINE_PAY[$symbol][4], $alive); // runs alive after reel 5
        $way += $p * max([$dead, ...$five]);
    }
    $freeScatters = cellCountLaw($freeReels, 'SUNDISC');
    $perFreeSpin = $way + $scat($freeScatters, HouseOfRa::SCAT_PAY);
    $spins = $scat($base['scatters'], HouseOfRa::SCAT_FREESPIN) / (1 - $scat($freeScatters, HouseOfRa::SCAT_FREESPIN));
    $rtp = $base['line'] + $scat($base['scatters'], HouseOfRa::SCAT_PAY) + $spins * $perFreeSpin;

    expect(round(($base['line'] + $scat($base['scatters'], HouseOfRa::SCAT_PAY)) * 100, 6))->toBe(65.317603)
        ->and(round($rtp * 100, 6))->toBe(95.182099);
});

/**
 * The expected pay of one line whose cell on each reel has its own law (symbol => probability), walking reel by
 * reel and stopping as soon as the run is broken (the rest cannot change the pay).
 *
 * @param  list<array<string, float>>  $laws
 * @param  Closure(list<string>): ?array  $lineWin  [symbol, count, pay] or null
 */
function lineExpectation(array $laws, Closure $lineWin, string $wild): float
{
    $walk = function (int $reel, array $symbols, ?string $syml, float $prob) use (&$walk, $laws, $lineWin, $wild): float {
        if ($reel === count($laws)) {
            return $prob * ($lineWin($symbols)[2] ?? 0);
        }
        $sum = 0.0;
        foreach ($laws[$reel] as $symbol => $p) {
            $symbol = (string) $symbol;
            $broken = $symbol !== $wild && $syml !== null && $symbol !== $syml;
            $line = [...$symbols, $symbol];
            $sum += $broken
                ? $prob * $p * ($lineWin(array_pad($line, count($laws), '-'))[2] ?? 0)
                : $walk($reel + 1, $line, $syml ?? ($symbol === $wild ? null : $symbol), $prob * $p);
        }

        return $sum;
    };

    return $walk(0, [], null, 1.0);
}

it('deals Samurai at its published RTP of 95.388785 % (wild reels in the free games), exactly', function () {
    $scat = fn (array $law, array $values) => array_sum(array_map(fn ($n, $p) => $n > 0 ? $p * $values[min($n, 5) - 1] : 0, array_keys($law), $law));
    $base = boardLaw(Samurai::reels(Samurai::BASE), fn (array $l) => ($w = Samurai::lineWin($l)) ? [0, 0, 0, $w[2]] : null, 'GONG');

    // In a free game a cell reads wild when its reel shows a samurai anywhere: one law per reel and row.
    $freeReels = Samurai::reels(Samurai::FREE);
    $laws = [];
    foreach ($freeReels as $x => $strip) {
        for ($row = 0; $row < 3; $row++) {
            foreach (array_keys($strip) as $stop) {
                $window = [$strip[$stop], $strip[($stop + 1) % count($strip)], $strip[($stop + 2) % count($strip)]];
                $cell = in_array('SAMURAI', $window, true) ? 'SAMURAI' : $window[$row];
                $laws[$x][$row][$cell] = ($laws[$x][$row][$cell] ?? 0) + 1 / count($strip);
            }
        }
    }
    $freeLines = 0.0;
    foreach (Samurai::PAYLINES as $line) {
        $freeLines += lineExpectation(array_map(fn ($row, $x) => $laws[$x][$row], $line, array_keys($line)), Samurai::lineWin(...), 'SAMURAI') / 20;
    }
    $freeScatters = cellCountLaw($freeReels, 'GONG');
    $perFreeSpin = $freeLines + $scat($freeScatters, Samurai::SCAT_PAY);
    $spins = $scat($base['scatters'], Samurai::SCAT_FREESPIN) / (1 - $scat($freeScatters, Samurai::SCAT_FREESPIN));
    $rtp = $base['line'] + $scat($base['scatters'], Samurai::SCAT_PAY) + $spins * $perFreeSpin;

    expect(round($rtp * 100, 6))->toBe(95.388785);
});

it('deals The Dynasty at its published RTP of 94.138004 % (either heir calls, the other is crowned), exactly', function () {
    $baseReels = TheDynasty::reels(TheDynasty::BASE);
    $freeReels = TheDynasty::reels(TheDynasty::FREE);
    $base = boardLaw($baseReels, fn (array $l) => TheDynasty::lineWin($l), 'HEIRESS');

    // The joint law of (heiresses, heirs) on a base board: the heiress is checked first.
    $joint = [json_encode([0, 0]) => 1.0];
    foreach ($baseReels as $strip) {
        $counts = [];
        foreach (array_keys($strip) as $stop) {
            $window = [$strip[$stop], $strip[($stop + 1) % count($strip)], $strip[($stop + 2) % count($strip)]];
            $k = json_encode([count(array_keys($window, 'HEIRESS')), count(array_keys($window, 'HEIR'))]);
            $counts[$k] = ($counts[$k] ?? 0) + 1 / count($strip);
        }
        $next = [];
        foreach ($joint as $have => $p) {
            foreach ($counts as $add => $q) {
                [$a, $b] = json_decode($have);
                [$c, $d] = json_decode($add);
                $k = json_encode([$a + $c, $b + $d]);
                $next[$k] = ($next[$k] ?? 0) + $p * $q;
            }
        }
        $joint = $next;
    }
    $calls = ['HEIRESS' => 0.0, 'HEIR' => 0.0];
    foreach ($joint as $k => $p) {
        [$heiress, $heir] = json_decode($k);
        if ($heiress >= 3) {
            $calls['HEIRESS'] += $p;
        } elseif ($heir >= 3) {
            $calls['HEIR'] += $p;
        }
    }

    $draw = array_sum(TheDynasty::FREE_GAMES_DRAW) / count(TheDynasty::FREE_GAMES_DRAW);
    $rtp = $base['line'];
    foreach ($calls as $caller => $pCall) {
        $crowned = TheDynasty::otherHeir($caller);
        $line = boardLaw($freeReels, fn (array $l) => TheDynasty::lineWin($l, $crowned), $caller)['line'];
        $law = cellCountLaw($freeReels, $caller);
        $scat = array_sum(array_map(fn ($n, $p) => $n >= 3 ? $p * TheDynasty::SCAT_PAY[min($n, 5) - 1] : 0, array_keys($law), $law));
        $again = array_sum(array_filter($law, fn ($p, $n) => $n >= 3, ARRAY_FILTER_USE_BOTH));
        $rtp += $pCall * $draw / (1 - $draw * $again) * ($line + $scat);
    }

    expect(round($rtp * 100, 6))->toBe(94.138004);
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
