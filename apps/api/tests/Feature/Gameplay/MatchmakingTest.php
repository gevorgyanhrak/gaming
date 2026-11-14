<?php

use App\Enums\BotDifficulty;
use App\Enums\FishkaTransactionType;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Gameplay\Matchmaking\Matchmaker;
use App\Gameplay\Matchmaking\MatchmakingEvent;
use App\Models\BotProfile;
use App\Models\Game;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Event::fake([MatchmakingEvent::class]);
});

function withBots(GameMode $mode): void
{
    BotProfile::factory()->create()->gameProfiles()->create(['game_id' => $mode->game_id, 'difficulty' => BotDifficulty::Medium]);
}

it('pairs two waiting players into a room and tells both', function () {
    $mode = ticTacToe();
    [$alice, $bob] = User::factory()->count(2)->create();

    $this->actingAs($alice)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id])
        ->assertCreated()
        ->assertJsonPath('data.ticket.status', 'queued')
        ->assertJsonPath('data.room', null);

    $room = $this->actingAs($bob)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id])
        ->assertCreated()
        ->assertJsonPath('data.ticket', null)
        ->json('data.room');

    $created = GameRoom::query()->where('public_id', $room)->sole();
    expect($created->type)->toBe(RoomType::Matchmaking)
        ->and($created->status)->toBe(RoomStatus::Active)
        ->and($created->participants()->pluck('user_id')->sort()->values()->all())->toBe(collect([$alice->id, $bob->id])->sort()->values()->all());

    Event::assertDispatched(MatchmakingEvent::class, fn ($e) => $e->userId === $alice->id && $e->name === 'matched' && $e->payload['room'] === $room);
    $this->actingAs($alice)->getJson('/api/v1/matchmaking')->assertJsonPath('data.ticket', null)->assertJsonPath('data.room', $room);
});

it('does not pair players looking for different modes', function () {
    $mode = ticTacToe();
    $other = GameMode::factory()->for($mode->game)->create(['slug' => 'other']);
    [$alice, $bob] = User::factory()->count(2)->create();

    $this->actingAs($alice)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);
    $this->actingAs($bob)->postJson('/api/v1/matchmaking', ['game_mode_id' => $other->id])->assertJsonPath('data.ticket.status', 'queued');

    expect(GameRoom::query()->count())->toBe(0);
});

it('keeps one ticket per player and lets them leave the queue', function () {
    $mode = ticTacToe();
    $alice = User::factory()->create();
    $this->actingAs($alice);

    $this->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);
    $this->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id])->assertJsonPath('data.ticket.status', 'queued');
    expect(GameRoom::query()->count())->toBe(0);

    $this->deleteJson('/api/v1/matchmaking')->assertNoContent();
    $this->getJson('/api/v1/matchmaking')->assertJsonPath('data.ticket', null);

    $this->actingAs(User::factory()->create())->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);
    expect(GameRoom::query()->count())->toBe(0);
});

it('fills the empty seat with AI after the timeout, in an ordinary matchmaking room', function (string $fallback) {
    $mode = ticTacToe();
    withBots($mode);
    $mode->game->update(['bot_fallback_mode' => $fallback, 'bot_fallback_timeout_seconds' => 20]);
    $alice = User::factory()->create();
    $this->actingAs($alice)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id])
        // Nothing tells the player when, or whether, AI will sit down.
        ->assertJsonMissingPath('data.ticket.bot_offer_at')
        ->assertJsonPath('data.ticket.status', 'queued');

    app(Matchmaker::class)->sweep();
    expect(GameRoom::query()->count())->toBe(0);

    $this->travel(21)->seconds();
    $this->postJson('/api/v1/matchmaking/heartbeat');
    app(Matchmaker::class)->sweep();

    $room = GameRoom::query()->sole();
    expect($room->type)->toBe(RoomType::Matchmaking)
        ->and($room->has_bot)->toBeTrue()
        ->and($room->participants()->where('user_id', $alice->id)->exists())->toBeTrue()
        ->and($room->participants()->whereNotNull('bot_profile_id')->value('bot_difficulty'))->toBe(BotDifficulty::Medium);
    Event::assertDispatched(MatchmakingEvent::class, fn ($e) => $e->userId === $alice->id && $e->name === 'matched');
    Event::assertNotDispatched(MatchmakingEvent::class, fn ($e) => $e->name === 'bot_offered');
    $this->postJson('/api/v1/matchmaking/accept-bot')->assertNotFound();
})->with(['auto', 'offer']);

it('puts people together first: AI never takes a seat a person is waiting for', function () {
    $mode = ticTacToe();
    withBots($mode);
    $mode->game->update(['bot_fallback_timeout_seconds' => 20]);
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $this->actingAs($alice)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);
    $this->travel(15)->seconds();
    $this->actingAs($bob)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);

    $room = GameRoom::query()->sole();
    expect($room->has_bot)->toBeFalse()
        ->and($room->participants()->pluck('user_id')->sort()->values()->all())->toBe([$alice->id, $bob->id]);
});

it('never fills seats with AI when the game keeps waiting for people', function () {
    $mode = ticTacToe();
    withBots($mode);
    $mode->game->update(['bot_fallback_mode' => 'off', 'bot_fallback_timeout_seconds' => 10]);
    $this->actingAs(User::factory()->create())->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);

    $this->travel(11)->seconds();
    $this->postJson('/api/v1/matchmaking/heartbeat');
    app(Matchmaker::class)->sweep();

    expect(GameRoom::query()->count())->toBe(0);
    Event::assertNotDispatched(MatchmakingEvent::class);
});

it('drops tickets whose player stopped sending heartbeats', function () {
    $mode = ticTacToe();
    $this->actingAs(User::factory()->create())->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);

    $this->travel(Matchmaker::HEARTBEAT_TIMEOUT + 1)->seconds();
    app(Matchmaker::class)->sweep();

    $this->getJson('/api/v1/matchmaking')->assertJsonPath('data.ticket', null);
});

it('refuses to queue for unavailable modes', function () {
    $mode = ticTacToe(['matchmaking_enabled' => false]);

    $this->actingAs(User::factory()->create())->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id])
        ->assertJsonValidationErrors('game_mode_id');
});

it('shows game history and statistics', function () {
    $mode = ticTacToe();
    withBots($mode);
    $alice = User::factory()->create(['username' => 'alice']);
    $this->actingAs($alice);

    $id = $this->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'medium'])->json('data.id');
    $this->postJson("/api/v1/rooms/{$id}/leave");

    $this->getJson('/api/v1/me/games')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/me/stats')->assertOk()
        ->assertJsonPath('data.0.game.slug', 'tictactoe')
        ->assertJsonPath('data.0.played', 1)
        ->assertJsonPath('data.0.losses', 1);
    $this->getJson('/api/v1/users/alice')->assertJsonPath('data.stats.0.played', 1);

    $this->getJson('/api/v1/stats/online')->assertOk()->assertJsonPath('data.online', 1);
});

/** The seeded poker mode (2–6 players, tables wait 20 s for more players). */
function pokerQueue(): GameMode
{
    test()->seed([GameSeeder::class, GameModeSeeder::class]);

    return Game::query()->where('slug', 'poker')->sole()->modes()->sole();
}

it('waits for more players at bigger tables, then seats everyone waiting', function () {
    $mode = pokerQueue();
    $players = User::factory()->count(4)->create();

    foreach ($players as $player) {
        $this->actingAs($player)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id])
            ->assertCreated()->assertJsonPath('data.room', null);
    }
    $this->getJson('/api/v1/matchmaking')->assertJsonPath('data.waiting', 4);

    app(Matchmaker::class)->sweep();
    expect(GameRoom::query()->count())->toBe(0);

    $this->travel(21)->seconds();
    app(Matchmaker::class)->sweep();

    $room = GameRoom::query()->sole();
    expect($room->seats)->toBe(4)
        ->and($room->type)->toBe(RoomType::Matchmaking)
        ->and($room->participants()->pluck('user_id')->sort()->values()->all())->toBe($players->pluck('id')->sort()->values()->all());
    foreach ($players as $player) {
        Event::assertDispatched(MatchmakingEvent::class, fn ($e) => $e->userId === $player->id && $e->name === 'matched');
    }
});

it('starts a full table at once and queues the next player for another', function () {
    $mode = pokerQueue();
    $players = User::factory()->count(7)->create();

    foreach ($players->take(6) as $player) {
        $result = $this->actingAs($player)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id])->json('data');
    }
    expect($result['room'])->not->toBeNull()
        ->and(GameRoom::query()->sole()->seats)->toBe(6);

    $this->actingAs($players->last())->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id])
        ->assertJsonPath('data.room', null)
        ->assertJsonPath('data.waiting', 1);
});

it('never starts a bigger table with a single player', function () {
    $mode = pokerQueue();
    $mode->game->update(['bots_enabled' => false]);
    $this->actingAs(User::factory()->create())->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);

    $this->travel(30)->seconds();
    $this->postJson('/api/v1/matchmaking/heartbeat');
    app(Matchmaker::class)->sweep();

    expect(GameRoom::query()->count())->toBe(0);
});

it('seats everyone waiting at a bigger table and fills the rest with AI', function () {
    $mode = pokerQueue();
    withBots($mode);
    $mode->game->update(['bot_fallback_timeout_seconds' => 10]);
    $alice = User::factory()->create();
    $this->actingAs($alice)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);

    $this->travel(11)->seconds();
    $this->postJson('/api/v1/matchmaking/heartbeat');
    app(Matchmaker::class)->sweep();

    $room = GameRoom::query()->sole();
    expect($room->type)->toBe(RoomType::Matchmaking)
        ->and($room->participants()->count())->toBe(Matchmaker::BOT_TABLE_SIZE)
        ->and($room->participants()->whereNotNull('user_id')->pluck('user_id')->all())->toBe([$alice->id]);
});

it('finds the player’s ticket in their group’s queue, and only matches within a group', function () {
    $mode = ticTacToe();
    [$alice, $bob] = User::factory()->count(2)->create();
    app(FishkaWallets::class)->credit($bob, 50_000, FishkaTransactionType::AdminCredit);

    $this->actingAs($alice)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id])->assertCreated();
    // Bob waits in a higher group: no match with Alice.
    $this->actingAs($bob)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id, 'group' => 'casual'])
        ->assertCreated()->assertJsonPath('data.room', null)->assertJsonPath('data.waiting', 1);

    $this->actingAs($alice)->getJson('/api/v1/matchmaking')->assertJsonPath('data.ticket.status', 'queued')->assertJsonPath('data.waiting', 1);
    $this->postJson('/api/v1/matchmaking/heartbeat')->assertJsonPath('data.ticket.status', 'queued');
    $this->deleteJson('/api/v1/matchmaking')->assertNoContent();
    $this->getJson('/api/v1/matchmaking')->assertJsonPath('data.ticket', null);
});

it('marks the AI at a table being prepared, and only the AI (ADR 0022)', function () {
    $mode = pokerQueue();
    withBots($mode);
    $mode->game->update(['bot_fallback_timeout_seconds' => 5]);
    $alice = User::factory()->create();
    $this->actingAs($alice)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);

    $this->travel(6)->seconds();
    $this->postJson('/api/v1/matchmaking/heartbeat');
    app(Matchmaker::class)->sweep();

    $table = $this->getJson('/api/v1/matchmaking')->json('data.table');
    expect($table)->toBeArray()->not->toBeEmpty()
        ->and(collect($table)->where('is_bot', false)->pluck('name')->all())->toBe([$alice->profile->display_name ?? $alice->username])
        ->and(collect($table)->where('is_bot', true)->count())->toBe(count($table) - 1);
});
