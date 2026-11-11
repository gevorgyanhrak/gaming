<?php

use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Lifecycle\ResumeRoundJob;
use App\Gameplay\Rooms\RoomStates;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(fn () => Queue::fake());

it('plays the break on the server and waits while the balls roll', function () {
    $this->seed([GameSeeder::class, GameModeSeeder::class]);
    $mode = Game::query()->where('slug', 'pool')->sole()->modes()->sole();
    [$alice, $bob] = User::factory()->count(2)->create();
    $data = $this->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private'])->assertCreated()->json('data');
    $this->actingAs($bob)->postJson('/api/v1/rooms/join', ['invite_code' => $data['invite_code']])->assertOk();
    $room = GameRoom::query()->where('public_id', $data['id'])->sole();
    $post = fn (User $user, array $action, int $version) => $this->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/actions", [
        'client_action_id' => (string) Str::uuid(), 'expected_version' => $version, 'action' => $action,
    ]);

    // Clients send only the aim: no positions without ball in hand, no power over 100.
    $post($alice, ['type' => 'shoot', 'angle' => 0, 'power' => 150], 0)->assertUnprocessable();
    $post($alice, ['type' => 'shoot', 'angle' => 0, 'power' => 90, 'x' => 600, 'y' => 600], 0)->assertUnprocessable();
    $post($bob, ['type' => 'shoot', 'angle' => 0, 'power' => 90], 0)->assertUnprocessable();
    $post($alice, ['type' => 'shoot', 'angle' => 0.5, 'power' => 95], 0)->assertOk();

    $states = app(RoomStates::class);
    $current = $states->current($room->refresh()->load(['game', 'participants']));
    expect($current->seatsToAct)->toBe([])
        ->and($current->state['last_shot']['segments'])->not->toBeEmpty()
        ->and(strlen(json_encode($current->state['last_shot'])))->toBeLessThan(8000);
    Queue::assertPushed(ResumeRoundJob::class);

    (new ResumeRoundJob($room->id, $current->version))->handle(app(GameActionService::class));
    expect($states->current($room->refresh()->load(['game', 'participants']))->seatsToAct)->toHaveCount(1);
});
