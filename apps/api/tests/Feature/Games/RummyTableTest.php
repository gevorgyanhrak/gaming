<?php

use App\Gameplay\Rooms\RoomState;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(fn () => Queue::fake());

it('keeps hands and the stock secret, and takes melds through the API', function () {
    $this->seed([GameSeeder::class, GameModeSeeder::class]);
    $mode = Game::query()->where('slug', 'rummy')->sole()->modes()->sole();
    [$alice, $bob, $carol] = User::factory()->count(3)->create();
    $data = $this->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private', 'seats' => 3])->assertCreated()->json('data');
    foreach ([$bob, $carol] as $user) {
        $this->actingAs($user)->postJson('/api/v1/rooms/join', ['invite_code' => $data['invite_code']])->assertOk();
    }
    $room = GameRoom::query()->where('public_id', $data['id'])->sole()->load(['game', 'participants']);
    $states = app(RoomStates::class);
    $current = $states->current($room);

    $view = $this->actingAs($bob)->getJson("/api/v1/rooms/{$room->public_id}/state")->json('data.view');
    expect($view['hand_sizes'])->toBe([7, 7, 7])
        ->and($view)->not->toHaveKeys(['hands', 'stock', 'discard'])
        ->and($view['discard_top'])->toBe($current->state['discard'][0]);

    // The player to act holds a set after drawing.
    $seat = $current->seatsToAct[0];
    $user = User::query()->find($room->participants->firstWhere('seat', $seat)->user_id);
    $live = $current->state;
    $live['hands'][$seat] = ['7S', '7H', '7D', 'KC', '2D', '9H', 'QS', 'JD'];
    $live['phase'] = 'play';
    app(RoomStateStore::class)->put($room, new RoomState($current->version, $live, [$seat], $current->deadline, $current->clock));
    $post = fn (array $action) => $this->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/actions", [
        'client_action_id' => (string) Str::uuid(), 'expected_version' => $current->version, 'action' => $action,
    ]);

    $post(['type' => 'meld', 'cards' => '7S,7H,KC'])->assertUnprocessable();
    $post(['type' => 'meld', 'cards' => '7S,7H,7D'])->assertOk();

    expect($states->current($room->refresh())->state['melds'][0]['cards'])->toBe(['7S', '7H', '7D']);
});
