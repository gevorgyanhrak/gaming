<?php

use App\Enums\BotDifficulty;
use App\Enums\RoomStatus;
use App\Gameplay\Rooms\RoomState;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\BotProfile;
use App\Models\Game;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function dominoMode(): GameMode
{
    test()->seed([GameSeeder::class, GameModeSeeder::class]);

    return Game::query()->where('slug', 'domino')->sole()->modes()->sole();
}

beforeEach(fn () => Queue::fake());

it('opens tables of two to four and hides the other hands', function () {
    $mode = dominoMode();
    [$alice, $bob, $carol] = User::factory()->count(3)->create();
    $data = $this->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private', 'seats' => 3])->assertCreated()->json('data');
    foreach ([$bob, $carol] as $user) {
        $this->actingAs($user)->postJson('/api/v1/rooms/join', ['invite_code' => $data['invite_code']])->assertOk();
    }
    $room = GameRoom::query()->where('public_id', $data['id'])->sole()->load(['game', 'participants']);
    $live = app(RoomStates::class)->current($room)->state;

    $view = $this->actingAs($bob)->getJson("/api/v1/rooms/{$room->public_id}/state")->assertOk()->json('data.view');
    expect($room->status)->toBe(RoomStatus::Active)
        ->and($view['hand'])->toBe($live['hands'][1])
        ->and($view['hand_sizes'])->toBe([7, 7, 7])
        ->and($view)->not->toHaveKey('hands');
});

it('plays a tile at an end through the API', function () {
    $mode = dominoMode();
    BotProfile::factory()->create()->gameProfiles()->create(['game_id' => $mode->game_id, 'difficulty' => BotDifficulty::Easy]);
    $user = User::factory()->create();
    $data = $this->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'easy'])->assertCreated()->json('data');
    $room = GameRoom::query()->where('public_id', $data['id'])->sole()->load(['game', 'participants']);
    // The person to play, holding 3-5, against a line showing 3 and 4.
    $seat = $room->participants->firstWhere('user_id', $user->id)->seat;
    $current = app(RoomStates::class)->current($room);
    $live = $current->state;
    $live['hands'][$seat] = ['3-5', '6-6'];
    $live['line'] = [['tile' => [3, 4], 'seat' => 1 - $seat]];
    $live['lead_tile'] = null;
    $live['to_act'] = $seat;
    app(RoomStateStore::class)->put($room, new RoomState($current->version, $live, [$seat], $current->deadline, $current->clock));
    $state = $this->actingAs($user)->getJson("/api/v1/rooms/{$room->public_id}/state")->json('data');
    expect($state['legal_actions'])->toBe([['type' => 'play', 'end' => 'left', 'tile' => '3-5']]);

    $action = $state['legal_actions'][0];
    $this->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/actions", [
        'client_action_id' => (string) Str::uuid(), 'expected_version' => $state['version'], 'action' => ['type' => 'play', 'tile' => '9-9', 'end' => 'left'],
    ])->assertUnprocessable();
    $this->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/actions", [
        'client_action_id' => (string) Str::uuid(), 'expected_version' => $state['version'], 'action' => $action,
    ])->assertOk();

    expect(array_column(app(RoomStates::class)->current($room->refresh())->state['line'], 'tile'))->toBe([[5, 3], [3, 4]]);
});
