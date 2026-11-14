<?php

use App\Enums\UserStatus;
use App\Models\Game;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->seed(GameSeeder::class));

it('shows a player’s card: level, Fishkas, record, biggest win, favourite game, streak, online', function () {
    $ani = User::factory()->create(['last_active_at' => now()->subMinute()]);
    $staff = User::factory()->create();
    app(FishkaWallets::class)->adjust($ani, $staff, 'set', 7_500, 'test');
    $blot = Game::query()->where('slug', 'blot')->value('id');
    $poker = Game::query()->where('slug', 'poker')->value('id');
    foreach ([[$blot, 12, 9, 3, 4], [$poker, 3, 1, 2, 0]] as [$game, $played, $wins, $losses, $streak]) {
        DB::table('user_game_stats')->insert(['user_id' => $ani->id, 'game_id' => $game, 'played' => $played, 'wins' => $wins, 'losses' => $losses, 'draws' => 0, 'abandoned' => 0, 'current_streak' => $streak, 'best_streak' => $streak, 'created_at' => now(), 'updated_at' => now()]);
    }
    DB::table('game_wins')->insert([
        ['user_id' => $ani->id, 'game_id' => $poker, 'room_public_id' => 'a', 'amount' => 20_000, 'won_at' => now()],
        ['user_id' => $ani->id, 'game_id' => $blot, 'room_public_id' => 'b', 'amount' => 900, 'won_at' => now()],
    ]);

    $this->getJson("/api/v1/users/{$ani->username}/card")->assertOk()
        ->assertJsonPath('data.balance', 7_500)
        ->assertJsonPath('data.played', 15)->assertJsonPath('data.wins', 10)->assertJsonPath('data.losses', 5)
        ->assertJsonPath('data.win_rate', 67)
        ->assertJsonPath('data.biggest_win.amount', 20_000)->assertJsonPath('data.biggest_win.game.slug', 'poker')
        ->assertJsonPath('data.favourite_game.slug', 'blot')
        ->assertJsonPath('data.streak', 4)
        ->assertJsonPath('data.online', true)
        ->assertJsonPath('data.playing', null)
        ->assertJsonMissingPath('data.email');
});

it('has no card for a banned player or an unknown name', function () {
    $banned = User::factory()->create(['status' => UserStatus::Banned]);
    $this->getJson("/api/v1/users/{$banned->username}/card")->assertNotFound();
    $this->getJson('/api/v1/users/nobody-here/card')->assertNotFound();
});
