<?php

use App\Enums\BotDifficulty;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Gameplay\Matchmaking\Matchmaker;
use App\Gameplay\Matchmaking\MatchmakingEvent;
use App\Models\BotProfile;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
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

it('offers a bot game after the timeout and starts it when accepted', function () {
    $mode = ticTacToe();
    withBots($mode);
    $mode->game->update(['bot_fallback_timeout_seconds' => 20]);
    $alice = User::factory()->create();
    $this->actingAs($alice)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);

    app(Matchmaker::class)->sweep();
    $this->postJson('/api/v1/matchmaking/accept-bot')->assertUnprocessable();

    $this->travel(21)->seconds();
    $this->postJson('/api/v1/matchmaking/heartbeat');
    app(Matchmaker::class)->sweep();

    Event::assertDispatched(MatchmakingEvent::class, fn ($e) => $e->userId === $alice->id && $e->name === 'bot_offered');
    $this->getJson('/api/v1/matchmaking')->assertJsonPath('data.ticket.status', 'bot_offered');

    $room = $this->postJson('/api/v1/matchmaking/accept-bot', ['bot_difficulty' => 'medium'])->assertCreated()->json('data.room');
    expect(GameRoom::query()->where('public_id', $room)->sole()->has_bot)->toBeTrue();
});

it('starts a bot game automatically when the game is set to', function () {
    $mode = ticTacToe();
    withBots($mode);
    $mode->game->update(['bot_fallback_mode' => 'auto', 'bot_fallback_timeout_seconds' => 10]);
    $alice = User::factory()->create();
    $this->actingAs($alice)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id]);

    $this->travel(11)->seconds();
    $this->postJson('/api/v1/matchmaking/heartbeat');
    app(Matchmaker::class)->sweep();

    expect(GameRoom::query()->where('type', RoomType::Bot)->sole()->participants()->where('user_id', $alice->id)->exists())->toBeTrue();
    Event::assertDispatched(MatchmakingEvent::class, fn ($e) => $e->userId === $alice->id && $e->name === 'matched');
});

it('never offers bots when bots are off for the game', function () {
    $mode = ticTacToe();
    withBots($mode);
    $mode->game->update(['bot_fallback_mode' => 'off', 'bot_fallback_timeout_seconds' => 10]);
    $this->actingAs(User::factory()->create())->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id])
        ->assertJsonPath('data.ticket.bot_offer_at', null);

    $this->travel(11)->seconds();
    $this->postJson('/api/v1/matchmaking/heartbeat');
    app(Matchmaker::class)->sweep();

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
