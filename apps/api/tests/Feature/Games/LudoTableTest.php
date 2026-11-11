<?php

use App\Gameplay\Rooms\RoomStates;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(fn () => Queue::fake());

it('rolls on the server and only accepts the moves it lists', function () {
    $this->seed([GameSeeder::class, GameModeSeeder::class]);
    $mode = Game::query()->where('slug', 'ludo')->sole()->modes()->sole();
    [$alice, $bob] = User::factory()->count(2)->create();
    $data = $this->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private', 'seats' => 2])->assertCreated()->json('data');
    $this->actingAs($bob)->postJson('/api/v1/rooms/join', ['invite_code' => $data['invite_code']])->assertOk();
    $room = GameRoom::query()->where('public_id', $data['id'])->sole();
    $post = fn (User $user, array $action, int $version) => $this->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/actions", [
        'client_action_id' => (string) Str::uuid(), 'expected_version' => $version, 'action' => $action,
    ]);

    // A client can't choose the dice or move before rolling.
    $post($alice, ['type' => 'roll', 'dice' => 6], 0)->assertUnprocessable();
    $post($alice, ['type' => 'move', 'token' => 0, 'to' => 0], 0)->assertUnprocessable();
    $post($bob, ['type' => 'roll'], 0)->assertUnprocessable();
    $post($alice, ['type' => 'roll'], 0)->assertOk();

    $state = app(RoomStates::class)->current($room->refresh()->load('game'))->state;
    expect($state['dice'])->toBeBetween(1, 6)
        ->and($room->moves()->where('type', 'roll')->sole()->rng)->toBe([$state['dice']]);
});
