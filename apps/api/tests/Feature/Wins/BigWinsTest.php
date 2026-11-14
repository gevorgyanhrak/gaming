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

it('lists the biggest wins of the week, biggest first, and celebrates the big ones', function () {
    [$alice] = aliceBeatsBob();
    $small = winner(1);
    DB::table('game_wins')->insert([
        ['user_id' => $small->id, 'game_id' => Game::query()->where('slug', 'chess')->value('id'), 'room_public_id' => 'r-small', 'amount' => 500, 'won_at' => now()],
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
        ->assertJsonPath('data.1.amount', 500)
        ->assertJsonPath('data.1.celebrate', false);
});

it('never lists AI: it has no wallet, so it never wins Fishkas', function () {
    $mode = Game::query()->where('slug', 'checkers')->sole()->modes()->first();
    $game = Game::query()->where('slug', 'checkers')->sole();
    BotProfile::factory()->create()->gameProfiles()->create(['game_id' => $game->id, 'difficulty' => BotDifficulty::Hard]);
    $alice = winner(5_000);
    $room = $this->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot'])->assertCreated()->json('data');
    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room['id']}/leave")->assertOk();

    expect(DB::table('game_wins')->count())->toBe(0);
});
