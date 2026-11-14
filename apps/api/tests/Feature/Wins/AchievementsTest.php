<?php

use App\Models\Game;
use App\Models\User;
use App\Platform\Achievements\AchievementCatalog;
use App\Platform\Achievements\Achievements;
use App\Platform\Achievements\AchievementUnlocked;
use Carbon\CarbonImmutable;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(GameSeeder::class);
    Event::fake([AchievementUnlocked::class]);
});

function gameId(string $slug): int
{
    return (int) Game::query()->where('slug', $slug)->value('id');
}

function stats(User $user, string $slug, int $played, int $streak): void
{
    DB::table('user_game_stats')->insert(['user_id' => $user->id, 'game_id' => gameId($slug), 'played' => $played, 'wins' => 0, 'losses' => 0, 'draws' => 0, 'abandoned' => 0, 'current_streak' => $streak, 'best_streak' => $streak, 'created_at' => now(), 'updated_at' => now()]);
}

it('gives a big win per main game, a streak and experience — once each — and tells the person', function () {
    $ani = User::factory()->create();
    DB::table('game_wins')->insert(['user_id' => $ani->id, 'game_id' => gameId('poker'), 'room_public_id' => 'r1', 'amount' => AchievementCatalog::BIG_WIN, 'won_at' => now()]);
    stats($ani, 'poker', 100, AchievementCatalog::STREAK);

    app(Achievements::class)->afterGame($ani->id, gameId('poker'), 'r1');
    app(Achievements::class)->afterGame($ani->id, gameId('poker'), 'r1');

    expect(collect(app(Achievements::class)->of($ani->id))->pluck('key')->sort()->values()->all())->toBe(['big_win', 'streak', 'veteran_100'])
        ->and(collect(app(Achievements::class)->of($ani->id))->firstWhere('key', 'big_win')['game']['slug'])->toBe('poker');
    Event::assertDispatchedTimes(AchievementUnlocked::class, 3);
});

it('gives no big win below the amount, and none for games that are not main games', function () {
    $ani = User::factory()->create();
    DB::table('game_wins')->insert([
        ['user_id' => $ani->id, 'game_id' => gameId('blot'), 'room_public_id' => 'r1', 'amount' => AchievementCatalog::BIG_WIN - 1, 'won_at' => now()],
        ['user_id' => $ani->id, 'game_id' => gameId('chess'), 'room_public_id' => 'r2', 'amount' => 50_000, 'won_at' => now()],
    ]);

    app(Achievements::class)->afterGame($ani->id, gameId('blot'), 'r1');
    app(Achievements::class)->afterGame($ani->id, gameId('chess'), 'r2');

    expect(app(Achievements::class)->of($ani->id))->toBe([]);
});

it('names the day’s best player and the week’s champion once the period is over', function () {
    [$ani, $gor] = User::factory()->count(2)->create();
    $day = CarbonImmutable::parse('2026-11-17 12:00');
    DB::table('game_wins')->insert([
        ['user_id' => $ani->id, 'game_id' => gameId('blot'), 'room_public_id' => 'r1', 'amount' => 3_000, 'won_at' => $day],
        ['user_id' => $gor->id, 'game_id' => gameId('poker'), 'room_public_id' => 'r2', 'amount' => 5_000, 'won_at' => $day],
    ]);

    app(Achievements::class)->closePeriod('day', $day);
    app(Achievements::class)->closePeriod('week', $day);

    expect(collect(app(Achievements::class)->of($gor->id))->pluck('key')->sort()->values()->all())->toBe(['daily_best', 'weekly_champion'])
        ->and(collect(app(Achievements::class)->of($gor->id))->firstWhere('key', 'daily_best')['period'])->toBe('2026-11-17')
        ->and(app(Achievements::class)->of($ani->id))->toBe([]);
});

it('shows a player’s achievements on their public profile', function () {
    $ani = User::factory()->create();
    stats($ani, 'blot', 500, 0);
    app(Achievements::class)->afterGame($ani->id, gameId('blot'), 'none');

    $this->getJson("/api/v1/users/{$ani->username}")->assertOk()
        ->assertJsonCount(2, 'data.achievements')
        ->assertJsonPath('data.achievements.0.key', fn (string $k) => str_starts_with($k, 'veteran_'));
});
