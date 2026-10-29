<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Bots\BotTurnJob;
use App\Models\BotProfile;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
use App\Platform\Settings\Settings;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => Queue::fake());

function botFor(GameMode $mode, BotDifficulty $difficulty = BotDifficulty::Easy): BotProfile
{
    $bot = BotProfile::factory()->create(['name' => 'Aram']);
    $bot->gameProfiles()->create(['game_id' => $mode->game_id, 'difficulty' => $difficulty]);

    return $bot;
}

function createRoom(GameMode $mode, array $data = []): TestResponse
{
    return test()->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'easy', ...$data]);
}

it('starts a game against a bot', function () {
    $mode = ticTacToe();
    botFor($mode);
    $this->actingAs(User::factory()->create(['username' => 'alice']));

    $id = createRoom($mode)
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.type', 'bot')
        ->assertJsonPath('data.participants.0.username', 'alice')
        ->assertJsonPath('data.participants.1.is_bot', true)
        ->assertJsonPath('data.participants.1.name', 'Aram')
        // What "Play again" needs to start the same kind of game.
        ->assertJsonPath('data.mode.id', $mode->id)
        ->assertJsonPath('data.participants.0.bot_difficulty', null)
        ->assertJsonPath('data.participants.1.bot_difficulty', 'easy')
        ->json('data.id');

    $state = $this->getJson("/api/v1/rooms/{$id}/state")->assertOk()->json('data');
    expect($state['version'])->toBe(0)->and($state['seat'])->toBe(0);

    if ($state['seats_to_act'] === [0]) {
        expect($state['legal_actions'])->toHaveCount(9);
    } else {
        expect($state['legal_actions'])->toBe([]);
        Queue::assertPushed(BotTurnJob::class);
    }
});

it('refuses unavailable games, disabled bots and missing difficulties', function (Closure $setup, array $data, string $field) {
    $mode = ticTacToe();
    botFor($mode);
    $setup($mode);
    $this->actingAs(User::factory()->create());

    createRoom($mode, $data)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'game not playable' => [fn (GameMode $m) => $m->game->update(['is_enabled' => false]), [], 'game_mode_id'],
    'mode disabled' => [fn (GameMode $m) => $m->update(['is_enabled' => false]), [], 'game_mode_id'],
    'bots disabled' => [fn (GameMode $m) => $m->game->update(['bots_enabled' => false]), [], 'game_mode_id'],
    'no bot at difficulty' => [fn () => null, ['bot_difficulty' => 'hard'], 'bot_difficulty'],
    'private rooms disabled' => [fn (GameMode $m) => $m->update(['private_rooms_enabled' => false]), ['type' => 'private'], 'game_mode_id'],
]);

it('requires a verified contact to play when the settings say so', function () {
    $mode = ticTacToe();
    botFor($mode);
    $this->actingAs(User::factory()->unverified()->create());

    createRoom($mode)->assertForbidden()->assertJsonPath('reason', 'verification_required')->assertJsonPath('channel', 'email');

    app(Settings::class)->update(['access.require_email_verification' => false]);
    createRoom($mode)->assertCreated();
});

it('limits how many games a player has at once', function () {
    $mode = ticTacToe();
    botFor($mode);
    $this->actingAs(User::factory()->create());

    createRoom($mode)->assertCreated();
    createRoom($mode)->assertUnprocessable()->assertJsonValidationErrors('game_mode_id');

    app(Settings::class)->update(['matchmaking.max_concurrent_games' => 2]);
    createRoom($mode)->assertCreated();
});

it('opens a private room that starts when a friend joins with the code', function () {
    $mode = ticTacToe();
    [$alice, $bob, $carol] = User::factory()->count(3)->create();

    $this->actingAs($alice);
    $room = $this->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'waiting')
        ->json('data');
    expect($room['invite_code'])->toMatch('/^[A-Z2-9]{6}$/');

    $this->actingAs($bob)->postJson('/api/v1/rooms/join', ['invite_code' => strtolower($room['invite_code'])])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.invite_code', null)
        ->assertJsonCount(2, 'data.participants');

    $this->actingAs($carol)->postJson('/api/v1/rooms/join', ['invite_code' => $room['invite_code']])
        ->assertJsonValidationErrors('invite_code');
});

it('plays moves over HTTP with version checks', function () {
    Exceptions::fake();
    $mode = ticTacToe();
    [$alice, $bob] = User::factory()->count(2)->create();
    $this->actingAs($alice);
    $code = $this->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private'])->json('data.invite_code');
    $id = $this->actingAs($bob)->postJson('/api/v1/rooms/join', ['invite_code' => $code])->json('data.id');

    $room = GameRoom::query()->where('public_id', $id)->firstOrFail();
    $first = $this->actingAs($alice)->getJson("/api/v1/rooms/{$id}/state")->json('data.seats_to_act.0') === 0 ? $alice : $bob;
    $second = $first->is($alice) ? $bob : $alice;
    $move = fn (User $user, int $cell, int $version) => $this->actingAs($user)->postJson("/api/v1/rooms/{$id}/actions", [
        'client_action_id' => (string) Str::uuid(),
        'expected_version' => $version,
        'action' => ['type' => 'place', 'cell' => $cell],
    ]);

    $move($second, 0, 0)->assertUnprocessable()->assertJsonPath('message', __('platform.play.not_your_turn'));
    $move($first, 4, 0)->assertOk()->assertJsonPath('data.version', 1)->assertJsonPath('data.legal_actions', []);
    $move($second, 4, 1)->assertUnprocessable();
    $move($second, 0, 0)->assertConflict()->assertJsonPath('current_version', 1);

    expect($room->moves()->count())->toBe(2);
    // Out-of-turn, illegal and stale moves are normal play, not errors to report.
    Exceptions::assertNothingReported();
});

it('only accepts small, flat action objects', function (array $action) {
    $mode = ticTacToe();
    [$alice, $bob] = User::factory()->count(2)->create();
    $code = $this->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private'])->json('data.invite_code');
    $id = $this->actingAs($bob)->postJson('/api/v1/rooms/join', ['invite_code' => $code])->json('data.id');

    $this->postJson("/api/v1/rooms/{$id}/actions", [
        'client_action_id' => (string) Str::uuid(),
        'expected_version' => 0,
        'action' => $action,
    ])->assertUnprocessable()->assertJsonValidationErrors('action');
})->with([
    'nested' => [['type' => 'place', 'cell' => [4]]],
    'long text' => [['type' => 'place', 'cell' => str_repeat('x', 65)]],
    'too many fields' => [['type' => 'place', 'a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6, 'g' => 7, 'h' => 8]],
]);

it('hides rooms from players who are not in them', function () {
    $mode = ticTacToe();
    botFor($mode);
    $this->actingAs(User::factory()->create());
    $id = createRoom($mode)->json('data.id');

    $this->actingAs(User::factory()->create());
    $this->getJson("/api/v1/rooms/{$id}")->assertForbidden();
    $this->getJson("/api/v1/rooms/{$id}/state")->assertForbidden();
    $this->postJson("/api/v1/rooms/{$id}/actions", [])->assertForbidden();
});

it('resigns when leaving a game, and closes a waiting room when its creator leaves', function () {
    $mode = ticTacToe();
    botFor($mode);
    $this->actingAs(User::factory()->create());

    $id = createRoom($mode)->json('data.id');
    $this->postJson("/api/v1/rooms/{$id}/leave")
        ->assertOk()
        ->assertJsonPath('data.status', 'finished')
        ->assertJsonPath('data.end_reason', 'resign')
        ->assertJsonPath('data.participants.0.result', 'loss');

    $waiting = $this->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private'])->json('data.id');
    $this->postJson("/api/v1/rooms/{$waiting}/leave")->assertJsonPath('data.status', 'aborted');
});

it('lists the rooms a player is currently in', function () {
    $mode = ticTacToe();
    botFor($mode);
    app(Settings::class)->update(['matchmaking.max_concurrent_games' => 3]);
    $this->actingAs(User::factory()->create());

    $active = createRoom($mode)->json('data.id');
    $finished = createRoom($mode)->json('data.id');
    $this->postJson("/api/v1/rooms/{$finished}/leave");

    $this->getJson('/api/v1/me/rooms')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $active);
});
