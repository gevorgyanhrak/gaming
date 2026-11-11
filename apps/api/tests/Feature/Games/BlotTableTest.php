<?php

use App\Enums\BotDifficulty;
use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Gameplay\Rooms\RoomStates;
use App\Models\BotProfile;
use App\Models\Game;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function blotMode(): GameMode
{
    test()->seed([GameSeeder::class, GameModeSeeder::class]);

    return Game::query()->where('slug', 'blot')->sole()->modes()->sole();
}

/** A private table of four people (beginner group: a 500 bet each). */
function blotTable(): array
{
    $mode = blotMode();
    $users = User::factory()->count(4)->create();
    $room = test()->actingAs($users[0])->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private'])->assertCreated()->json('data');
    foreach ($users->slice(1) as $user) {
        test()->actingAs($user)->postJson('/api/v1/rooms/join', ['invite_code' => $room['invite_code']])->assertOk();
    }

    return [GameRoom::query()->where('public_id', $room['id'])->sole()->load(['game', 'participants']), $users->all()];
}

beforeEach(fn () => Queue::fake());

it('starts a four-seat private table and shows each player only their own cards', function () {
    [$room, $users] = blotTable();
    expect($room->status)->toBe(RoomStatus::Active)->and($room->seats)->toBe(4);

    $live = app(RoomStates::class)->current($room)->state;
    $seat = $room->participants->firstWhere('user_id', $users[1]->id)->seat;
    $view = $this->actingAs($users[1])->getJson("/api/v1/rooms/{$room->public_id}/state")->assertOk()->json('data.view');

    expect($view['hand'])->toBe($live['hands'][$seat])
        ->and($view)->not->toHaveKey('hands')
        ->and($view['hand_sizes'])->toBe([8, 8, 8, 8]);
});

it('takes bids with a value and a suit through the API', function () {
    [$room, $users] = blotTable();
    $current = app(RoomStates::class)->current($room);
    $actor = User::query()->find($room->participants->firstWhere('seat', $current->seatsToAct[0])->user_id);
    $post = fn (array $action, int $version) => $this->actingAs($actor)->postJson("/api/v1/rooms/{$room->public_id}/actions", [
        'client_action_id' => (string) Str::uuid(), 'expected_version' => $version, 'action' => $action,
    ]);

    $post(['type' => 'bid', 'value' => 7, 'suit' => 'S'], $current->version)->assertUnprocessable();
    $post(['type' => 'bid', 'value' => 9, 'suit' => 'Z'], $current->version)->assertUnprocessable();
    $post(['type' => 'bid', 'value' => 9, 'suit' => 'S'], $current->version)->assertOk();

    expect(app(RoomStates::class)->current($room->refresh())->state['contract'])->toMatchArray(['value' => 9, 'suit' => 'S']);
});

it('makes the partner of a player who leaves lose too, and pays the other team', function () {
    [$room, $users] = blotTable();
    $wallets = app(FishkaWallets::class);
    $leaver = $room->participants->firstWhere('seat', 1);
    $before = $wallets->wallet($users[0])->balance;

    $this->actingAs(User::query()->find($leaver->user_id))->postJson("/api/v1/rooms/{$room->public_id}/leave")->assertOk();

    $room->refresh()->load('participants');
    $result = fn (int $seat) => $room->participants->firstWhere('seat', $seat)->result;
    expect($room->status)->toBe(RoomStatus::Finished)
        ->and($result(1))->toBe(ParticipantResult::Loss)
        ->and($result(3))->toBe(ParticipantResult::Loss)
        ->and($result(0))->toBe(ParticipantResult::Win)
        ->and($result(2))->toBe(ParticipantResult::Win)
        // The pot (4 × 500) goes to the two winners.
        ->and($wallets->wallet(User::query()->find($room->participants->firstWhere('seat', 0)->user_id))->balance)->toBe($before + 1000);
});

it('seats three bots at a bot table', function () {
    $mode = blotMode();
    BotProfile::factory()->create(['name' => 'Aram'])->gameProfiles()->create(['game_id' => $mode->game_id, 'difficulty' => BotDifficulty::Medium]);
    $user = User::factory()->create();

    $room = $this->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'medium', 'opponents' => 3])
        ->assertCreated()->json('data');

    expect($room['participants'])->toHaveCount(4);
    $this->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'medium', 'opponents' => 1])
        ->assertUnprocessable();
});
