<?php

use App\Enums\BotDifficulty;
use App\Models\BotProfile;
use App\Models\Game;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Settings\Settings;
use App\Platform\Wins\WinRecorded;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Notification::fake();
    Queue::fake();
    $this->seed([GameSeeder::class, GameModeSeeder::class]);
});

/** A person with $balance Fishkas. */
function winner(int $balance): User
{
    $user = User::factory()->create();
    $staff = User::query()->firstWhere('username', 'wins_staff') ?? User::factory()->create(['username' => 'wins_staff']);
    app(FishkaWallets::class)->adjust($user, $staff, 'set', $balance, 'test');

    return $user;
}

/** Alice and Bob at a checkers table for 3,000 each; Bob leaves, so Alice wins the pot. */
function aliceBeatsBob(): array
{
    $mode = Game::query()->where('slug', 'checkers')->sole()->modes()->first();
    [$alice, $bob] = [winner(5_000), winner(5_000)];
    $room = test()->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private', 'group' => 'casual', 'bet' => 3_000])->assertCreated()->json('data');
    test()->actingAs($bob)->postJson('/api/v1/rooms/join', ['invite_code' => $room['invite_code']])->assertOk();
    test()->actingAs($bob)->postJson("/api/v1/rooms/{$room['id']}/leave")->assertOk();

    return [$alice, $bob, $room['id']];
}

it('records what a person won in a game, from the ledger, and only for the winner', function () {
    Event::fake([WinRecorded::class]);
    [$alice, $bob, $room] = aliceBeatsBob();

    // Alice put in 3,000 and took the 6,000 pot: she won 3,000.
    expect(DB::table('game_wins')->where('user_id', $alice->id)->value('amount'))->toBe(3_000)
        ->and(DB::table('game_wins')->where('user_id', $bob->id)->exists())->toBeFalse()
        ->and(DB::table('game_wins')->where('room_public_id', $room)->count())->toBe(1);
    Event::assertDispatched(WinRecorded::class, fn (WinRecorded $e) => $e->win['amount'] === 3_000
        && $e->win['player']['username'] === $alice->username && $e->win['game']['slug'] === 'checkers'
        && $e->broadcastOn()[0]->name === 'wins');
});

it('lists the latest wins of the week, newest first, and celebrates the big ones', function () {
    [$alice] = aliceBeatsBob();
    $small = winner(1);
    DB::table('game_wins')->insert([
        ['user_id' => $small->id, 'game_id' => Game::query()->where('slug', 'chess')->value('id'), 'room_public_id' => 'r-small', 'amount' => 500, 'won_at' => now()->subMinute()],
        ['user_id' => $small->id, 'game_id' => Game::query()->where('slug', 'chess')->value('id'), 'room_public_id' => 'r-old', 'amount' => 900_000, 'won_at' => now()->subDays(8)],
    ]);
    app(Settings::class)->update(['wins.celebrate_at' => 2_000]);

    $this->getJson('/api/v1/wins')
        ->assertOk()
        ->assertJsonCount(2, 'data')   // last week's only
        ->assertJsonPath('data.0.amount', 3_000)
        ->assertJsonPath('data.0.player.username', $alice->username)
        ->assertJsonPath('data.0.game.slug', 'checkers')
        ->assertJsonPath('data.0.celebrate', true)
        ->assertJsonPath('data.0.is_ai', false)
        ->assertJsonPath('data.1.amount', 500)
        ->assertJsonPath('data.1.celebrate', false);
});

it('lists an AI player’s win in a real game, marked AI: never celebrated, never on leaderboards', function () {
    Event::fake([WinRecorded::class]);
    $mode = Game::query()->where('slug', 'checkers')->sole()->modes()->first();
    $game = Game::query()->where('slug', 'checkers')->sole();
    $bot = BotProfile::factory()->create(['name' => 'Aram']);
    $bot->gameProfiles()->create(['game_id' => $game->id, 'difficulty' => BotDifficulty::Hard]);
    $alice = winner(5_000);
    $room = $this->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'group' => 'casual', 'bet' => 3_000])->assertCreated()->json('data');
    // Alice leaves: the AI wins the pot (3,000 × 2 seats), which goes back to the house.
    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room['id']}/leave")->assertOk();

    expect(DB::table('game_wins')->count())->toBe(0)
        ->and(DB::table('ai_wins')->where('room_public_id', $room['id'])->value('amount'))->toBe(6_000);
    Event::assertDispatched(WinRecorded::class, fn (WinRecorded $e) => $e->win['is_ai'] === true
        && $e->win['player']['name'] === 'Aram' && $e->win['player']['username'] === null && $e->win['celebrate'] === false);

    app(Settings::class)->update(['wins.celebrate_at' => 1]);
    $this->getJson('/api/v1/wins')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_ai', true)
        ->assertJsonPath('data.0.amount', 6_000)
        ->assertJsonPath('data.0.celebrate', false);
    $this->getJson('/api/v1/leaderboards?period=week')->assertOk()->assertJsonCount(0, 'data');
});

it('records nothing for AI that didn’t win, nor at a table without a stake', function () {
    $mode = Game::query()->where('slug', 'checkers')->sole()->modes()->first();
    $game = Game::query()->where('slug', 'checkers')->sole();
    BotProfile::factory()->create()->gameProfiles()->create(['game_id' => $game->id, 'difficulty' => BotDifficulty::Hard]);
    $alice = winner(5_000);
    $room = $this->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot'])->assertCreated()->json('data');
    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room['id']}/leave")->assertOk();

    expect(DB::table('game_wins')->count())->toBe(0);
    if ((int) DB::table('game_rooms')->where('public_id', $room['id'])->value('stake') === 0) {
        expect(DB::table('ai_wins')->count())->toBe(0);
    }
});
