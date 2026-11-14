<?php

use App\Models\Game;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(GameSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-11-18 15:00:00'));   // a Wednesday
});

function won(User $user, string $game, int $amount, string $when): void
{
    static $n = 0;
    DB::table('game_wins')->insert([
        'user_id' => $user->id,
        'game_id' => Game::query()->where('slug', $game)->value('id'),
        'room_public_id' => 'room-'.(++$n),
        'amount' => $amount,
        'won_at' => CarbonImmutable::parse($when),
    ]);
}

it('ranks people by what they won in the period, with their best game', function () {
    [$ani, $hrak, $gor] = User::factory()->count(3)->create();
    won($ani, 'poker', 5_000, '2026-11-18 10:00');      // today
    won($ani, 'blot', 1_000, '2026-11-17 10:00');       // this week
    won($hrak, 'blackjack', 4_000, '2026-11-18 09:00'); // today
    won($hrak, 'blackjack', 3_000, '2026-11-18 11:00'); // today
    won($gor, 'blot', 9_000, '2026-11-03 10:00');       // this month only

    $this->getJson('/api/v1/leaderboards?period=day')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.player.username', $hrak->username)->assertJsonPath('data.0.total', 7_000)->assertJsonPath('data.0.wins', 2)
        ->assertJsonPath('data.0.game.slug', 'blackjack')
        ->assertJsonPath('data.1.player.username', $ani->username)->assertJsonPath('data.1.total', 5_000);

    $this->getJson('/api/v1/leaderboards?period=week')->assertOk()
        ->assertJsonPath('data.0.player.username', $hrak->username)
        ->assertJsonPath('data.1.total', 6_000)->assertJsonPath('data.1.game.slug', 'poker');

    $this->getJson('/api/v1/leaderboards?period=month')->assertOk()
        ->assertJsonPath('data.0.player.username', $gor->username)->assertJsonPath('data.0.rank', 1);
});

it('has a board per game', function () {
    [$ani, $gor] = User::factory()->count(2)->create();
    won($ani, 'poker', 5_000, '2026-11-18 10:00');
    won($gor, 'blot', 2_000, '2026-11-18 10:00');

    $this->getJson('/api/v1/leaderboards?period=day&game=blot')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.player.username', $gor->username)
        ->assertJsonPath('data.0.game.slug', 'blot');
});

it('refuses an unknown period or game', function () {
    $this->getJson('/api/v1/leaderboards?period=year')->assertUnprocessable();
    $this->getJson('/api/v1/leaderboards?game=roulette')->assertNotFound();
});
