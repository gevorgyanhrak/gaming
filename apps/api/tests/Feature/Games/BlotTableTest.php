<?php

use App\Enums\BotDifficulty;
use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Lifecycle\ResumeRoundJob;
use App\Gameplay\Rooms\RoomState;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Games\Blot\Engine\BlotCards;
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

/** Post an action for whoever is to act; returns the response. */
function blotMove(GameRoom $room, array $action, ?string $id = null)
{
    $states = app(RoomStates::class);
    $current = $states->current($room->refresh()->load(['game', 'participants']));
    $user = User::query()->find($room->participants->firstWhere('seat', $current->seatsToAct[0])->user_id);

    return test()->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/actions", [
        'client_action_id' => $id ?? (string) Str::uuid(), 'expected_version' => $current->version, 'action' => $action,
    ]);
}

it('plays a whole deal on the server, shows every player the same score, and does not end the match', function () {
    [$room, $users] = blotTable();
    $states = app(RoomStates::class);

    // Bid 8 in spades, three passes; then everyone plays their first playable card until the deal is over.
    blotMove($room, ['type' => 'bid', 'value' => 8, 'suit' => 'S'])->assertOk();
    foreach (range(1, 3) as $_) {
        blotMove($room, ['type' => 'pass'])->assertOk();
    }
    for ($i = 0; $i < 32; $i++) {
        $current = $states->current($room->refresh()->load(['game', 'participants']));
        $card = $states->engine($room)->playable($current->state, $current->seatsToAct[0])[0];
        blotMove($room, ['type' => 'play', 'card' => $card])->assertOk();
    }

    $live = $states->current($room->refresh()->load(['game', 'participants']))->state;
    expect($live['phase'])->toBe('over')
        ->and(array_sum($live['last_hand']['card_points']))->toBe(162);
    // One deal never decides the match — unless the bidders took no trick at all (reverse kaput, a rule).
    $bidders = $live['contract']['team'];
    if ($live['last_hand']['tricks'][$bidders] === 0) {
        expect($room->status)->toBe(RoomStatus::Finished)->and($live['result']['reason'])->toBe('reverse_kaput');

        return;
    }
    expect($room->status)->toBe(RoomStatus::Active)->and($live['result'])->toBeNull();
    $views = array_map(fn (User $u) => $this->actingAs($u)->getJson("/api/v1/rooms/{$room->public_id}/state")->json('data.view'), $users);
    foreach ($views as $view) {
        expect($view['scores'])->toBe($live['scores'])
            ->and($view['last_hand']['scored'])->toBe($live['last_hand']['scored']);
    }

    // The move log rebuilds the same table (a reconnect, or a restart of the state store).
    app(RoomStateStore::class)->forget($room);
    expect($states->rebuild($room)->state)->toBe($live);

    // The pause ends and the next deal starts with the scores carried over.
    (new ResumeRoundJob($room->id, $states->current($room)->version))->handle(app(GameActionService::class));
    $next = $states->current($room->refresh()->load(['game', 'participants']))->state;
    expect($next['hand_no'])->toBe(2)->and($next['scores'])->toBe($live['scores'])->and($next['phase'])->toBe('bidding');
});

it('accepts only declarations the server finds in the hand, once, and a repeated request only once', function () {
    [$room] = blotTable();
    $states = app(RoomStates::class);
    $current = $states->current($room);
    $live = $current->state;
    // Seat to act holds a terz in clubs; the bidding is over.
    $seat = $current->seatsToAct[0];
    $live['hands'][$seat] = ['7C', '8C', '9C', 'AH', 'KD', 'QS', 'TS', 'JD'];
    $live['contract'] = ['seat' => $seat, 'team' => $seat % 2, 'value' => 8, 'suit' => 'H', 'kaput' => false, 'doubled' => 0];
    $live['phase'] = 'playing';
    $live['leader'] = $seat;
    $live['available'][$seat] = BlotCards::declarations($live['hands'][$seat], 'H');
    app(RoomStateStore::class)->put($room, new RoomState($current->version, $live, [$seat], $current->deadline, $current->clock));

    // A made-up declaration (a name, points, an index that doesn't exist) is refused.
    blotMove($room, ['type' => 'declare', 'kind' => 'hundred', 'points' => 100])->assertUnprocessable();
    blotMove($room, ['type' => 'declare', 'index' => 3])->assertUnprocessable();

    $id = (string) Str::uuid();
    blotMove($room, ['type' => 'declare', 'index' => 0], $id)->assertOk();
    $after = $states->current($room->refresh()->load(['game', 'participants']));
    // The same request again changes nothing; declaring again is refused.
    $this->actingAs(User::query()->find($room->participants->firstWhere('seat', $seat)->user_id))
        ->postJson("/api/v1/rooms/{$room->public_id}/actions", ['client_action_id' => $id, 'expected_version' => $current->version, 'action' => ['type' => 'declare', 'index' => 0]])
        ->assertOk();
    blotMove($room, ['type' => 'declare', 'index' => 0])->assertUnprocessable();

    expect($after->state['declared'][$seat])->toHaveCount(1)
        ->and($states->current($room->refresh())->state['declared'][$seat])->toHaveCount(1)
        ->and($states->current($room)->version)->toBe($after->version);
});

it('ignores scores a client sends', function () {
    [$room] = blotTable();
    blotMove($room, ['type' => 'pass', 'scores' => '300,0'])->assertUnprocessable();

    expect(app(RoomStates::class)->current($room->refresh()->load('game'))->state['scores'])->toBe([0, 0]);
});
