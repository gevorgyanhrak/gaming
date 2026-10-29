<?php

use App\Enums\BotDifficulty;
use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Bots\BotSelector;
use App\Gameplay\Lifecycle\TurnTimeoutJob;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\BotProfile;
use App\Models\Game;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
use App\Models\UserGameStat;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function pokerMode(): GameMode
{
    test()->seed([GameSeeder::class, GameModeSeeder::class]);

    return Game::query()->where('slug', 'poker')->sole()->modes()->sole();
}

/** A private table of three people, started by joining with the invite code. */
function pokerTable(): array
{
    $mode = pokerMode();
    [$alice, $bob, $carol] = User::factory()->count(3)->create();
    $room = test()->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private', 'seats' => 3])->assertCreated()->json('data');
    foreach ([$bob, $carol] as $user) {
        test()->actingAs($user)->postJson('/api/v1/rooms/join', ['invite_code' => $room['invite_code']])->assertOk();
    }

    return [GameRoom::query()->where('public_id', $room['id'])->sole(), $alice, $bob, $carol];
}

beforeEach(fn () => Queue::fake());

it('seats as many bots as asked for', function () {
    $mode = pokerMode();
    $game = $mode->game;
    BotProfile::factory()->create()->gameProfiles()->create(['game_id' => $game->id, 'difficulty' => BotDifficulty::Easy]);
    $player = User::factory()->create();

    $room = $this->actingAs($player)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'easy', 'opponents' => 5])
        ->assertCreated()->json('data');

    expect($room['participants'])->toHaveCount(6);

    $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'easy', 'opponents' => 6])
        ->assertUnprocessable()->assertJsonValidationErrors('opponents');
});

it('seats different bots, borrowing from other levels before repeating one', function () {
    $mode = pokerMode();
    $bot = fn (string $name, BotDifficulty $difficulty) => BotProfile::factory()->create(['name' => $name])
        ->gameProfiles()->create(['game_id' => $mode->game->id, 'difficulty' => $difficulty]);
    $bot('Alex', BotDifficulty::Easy);
    $bot('David', BotDifficulty::Easy);
    foreach (['Aram', 'Nina', 'Levon'] as $name) {
        $bot($name, BotDifficulty::Hard);
    }

    $room = $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'easy', 'opponents' => 5])
        ->assertCreated()->json('data');

    $names = collect($room['participants'])->where('is_bot', true)->pluck('name');
    expect($names)->toHaveCount(5)
        ->and($names->unique())->toHaveCount(5)
        ->and($names)->toContain('Alex', 'David');
    // Borrowed bots still play at the table's level.
    expect(GameRoom::query()->where('public_id', $room['id'])->sole()->participants()->whereNotNull('bot_profile_id')->pluck('bot_difficulty')->unique()->values()->all())
        ->toBe([BotDifficulty::Easy]);
});

it('repeats a bot only when the game has fewer bots than seats, and tells the admin', function () {
    $mode = pokerMode();
    BotProfile::factory()->create(['name' => 'Alex'])->gameProfiles()->create(['game_id' => $mode->game->id, 'difficulty' => BotDifficulty::Easy]);
    BotProfile::factory()->create(['name' => 'David'])->gameProfiles()->create(['game_id' => $mode->game->id, 'difficulty' => BotDifficulty::Easy]);

    $room = $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'easy', 'opponents' => 3])
        ->assertCreated()->json('data');

    expect(collect($room['participants'])->where('is_bot', true))->toHaveCount(3);
    expect(app(BotSelector::class)->shortages())->toContain(
        ['game' => 'Poker', 'difficulty' => BotDifficulty::Easy, 'bots' => 2, 'needed' => 5],
    );
});

it('opens private tables of the chosen size', function () {
    [$room] = pokerTable();

    expect($room->seats)->toBe(3)->and($room->status)->toBe(RoomStatus::Active);
});

it('plays on when one of several people leaves, and ends when only one is left', function () {
    [$room, $alice, $bob, $carol] = pokerTable();

    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room->public_id}/leave")->assertOk();
    $room->refresh()->load('participants');
    $aliceSeat = $room->participants->firstWhere('user_id', $alice->id);

    expect($room->status)->toBe(RoomStatus::Active)
        ->and($aliceSeat->result)->toBe(ParticipantResult::Loss)
        ->and($aliceSeat->is_active)->toBeFalse()
        ->and(UserGameStat::query()->where('user_id', $alice->id)->sole()->losses)->toBe(1)
        ->and(app(RoomStates::class)->current($room->load('game'))->state['out'][$aliceSeat->seat])->toBeTrue();

    // The move log replays the departure.
    $live = app(RoomStates::class)->current($room)->state;
    app(RoomStateStore::class)->forget($room);
    expect(app(RoomStates::class)->rebuild($room)->state)->toBe($live);

    $this->actingAs($bob)->postJson("/api/v1/rooms/{$room->public_id}/leave")->assertOk();
    $room->refresh()->load('participants');

    expect($room->status)->toBe(RoomStatus::Finished)
        ->and($room->participants->firstWhere('user_id', $carol->id)->result)->toBe(ParticipantResult::Win)
        ->and($room->participants->firstWhere('user_id', $bob->id)->result)->toBe(ParticipantResult::Loss)
        ->and($room->participants->firstWhere('user_id', $alice->id)->result)->toBe(ParticipantResult::Loss)
        ->and(UserGameStat::query()->where('user_id', $alice->id)->sole()->played)->toBe(1);
});

it('folds for a player whose time runs out instead of ending the table', function () {
    [$room] = pokerTable();
    $room->load(['game', 'participants']);
    $state = app(RoomStates::class)->current($room);
    $seat = $state->seatsToAct[0];

    $this->travel(31)->seconds();
    (new TurnTimeoutJob($room->id, $state->version))->handle(app(GameActionService::class));

    $after = app(RoomStates::class)->current($room->refresh()->load(['game', 'participants']));
    expect($room->status)->toBe(RoomStatus::Active)
        ->and($after->version)->toBe($state->version + 1)
        ->and($after->state['folded'][$seat])->toBeTrue()
        ->and($room->moves()->where('seq', $after->version)->sole()->type)->toBe('fold');
});

it('accepts a raise of any size within the range and rejects others', function () {
    [$room, $alice, $bob, $carol] = pokerTable();
    $room->load(['game', 'participants']);
    $state = app(RoomStates::class)->current($room);
    $actor = $room->participants->firstWhere('seat', $state->seatsToAct[0]);
    $user = User::query()->find($actor->user_id);

    $post = fn (array $action, int $version) => $this->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/actions", [
        'client_action_id' => (string) Str::uuid(), 'expected_version' => $version, 'action' => $action,
    ]);

    $post(['type' => 'raise', 'amount' => 15], $state->version)->assertUnprocessable();
    $post(['type' => 'raise', 'min' => 20, 'max' => 1000], $state->version)->assertUnprocessable();
    $post(['type' => 'raise', 'amount' => 37], $state->version)->assertOk();

    expect(app(RoomStates::class)->current($room->refresh())->state['current_bet'])->toBe(37);
});

it('never shows other players’ cards through the API', function () {
    [$room, $alice] = pokerTable();
    $room->load(['game', 'participants']);
    $state = app(RoomStates::class)->current($room)->state;
    $mine = $room->participants->firstWhere('user_id', $alice->id)->seat;

    $body = $this->actingAs($alice)->getJson("/api/v1/rooms/{$room->public_id}/state")->assertOk()->getContent();

    foreach ($state['hole'] as $seat => $cards) {
        foreach ($cards as $card) {
            $seat === $mine ? expect($body)->toContain('"'.$card.'"') : expect($body)->not->toContain('"'.$card.'"');
        }
    }
});
