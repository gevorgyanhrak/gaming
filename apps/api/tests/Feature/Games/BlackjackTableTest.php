<?php

use App\Enums\BotDifficulty;
use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Bots\BotTurnJob;
use App\Gameplay\Lifecycle\ResumeRoundJob;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
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

function blackjackMode(): GameMode
{
    test()->seed([GameSeeder::class, GameModeSeeder::class]);

    return Game::query()->where('slug', 'blackjack')->sole()->modes()->sole();
}

function blackjackRoom(string $publicId): GameRoom
{
    return GameRoom::query()->where('public_id', $publicId)->sole()->load(['game', 'participants']);
}

/**
 * Play the room to its end: the person bets the minimum and stands, bots play through their jobs and
 * pauses end through the resume job.
 */
function playBlackjackOut(GameRoom $room, User $user): void
{
    $states = app(RoomStates::class);
    for ($step = 0; $step < 400; $step++) {
        $room = blackjackRoom($room->public_id);
        if ($room->status !== RoomStatus::Active) {
            return;
        }
        $current = $states->current($room);
        if ($current->seatsToAct === []) {
            (new ResumeRoundJob($room->id, $current->version))->handle(app(GameActionService::class));

            continue;
        }
        $participant = $room->participants->firstWhere('seat', $current->seatsToAct[0]);
        if ($participant->isBot()) {
            app()->call([new BotTurnJob($room->id, $current->version, $participant->seat), 'handle']);

            continue;
        }
        $legal = $states->engine($room)->legalActions($current->state, $participant->seat)[0];
        $action = $legal->type === 'bet' ? ['type' => 'bet', 'amount' => $legal->payload['min']] : ['type' => 'stand'];
        test()->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/actions", [
            'client_action_id' => (string) Str::uuid(), 'expected_version' => $current->version, 'action' => $action,
        ])->assertOk();
    }
    throw new RuntimeException('The game did not end.');
}

beforeEach(fn () => Queue::fake());

it('seats a player alone against the dealer with their whole balance as chips', function () {
    $mode = blackjackMode();
    $user = User::factory()->create();
    $balance = app(FishkaWallets::class)->wallet($user)->balance;

    $data = $this->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'opponents' => 0, 'group' => 'intermediate'])
        ->assertCreated()->json('data');

    $room = blackjackRoom($data['id']);
    expect($room->seats)->toBe(1)
        ->and($room->status)->toBe(RoomStatus::Active)
        ->and(app(FishkaWallets::class)->wallet($user)->balance)->toBe(0);

    $state = $this->actingAs($user)->getJson("/api/v1/rooms/{$room->public_id}/state")->assertOk()->json('data');
    expect($state['view']['stacks'])->toBe([$balance])
        ->and($state['view']['limits'])->toBe([5, 500])      // the group's minimum bet, 100 × it at most
        ->and($state['legal_actions'])->toBe([['type' => 'bet', 'max' => 500, 'min' => 5]])
        ->and($state['view'])->not->toHaveKey('shoe');
});

it('plays ten rounds and pays the chips back into the wallet', function () {
    $mode = blackjackMode();
    $user = User::factory()->create();
    $balance = app(FishkaWallets::class)->wallet($user)->balance;
    $data = $this->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'opponents' => 0])->assertCreated()->json('data');
    $room = blackjackRoom($data['id']);

    playBlackjackOut($room, $user);

    $room = blackjackRoom($room->public_id);
    $seat = $room->participants->sole();
    $net = app(FishkaWallets::class)->wallet($user)->balance - $balance;
    expect($room->status)->toBe(RoomStatus::Finished)
        ->and($room->moves()->where('type', 'bet')->count())->toBe(10)
        ->and($seat->score)->toBe($net)
        ->and($seat->result)->toBe(match (true) {
            $net > 0 => ParticipantResult::Win,
            $net < 0 => ParticipantResult::Loss,
            default => ParticipantResult::Draw,
        });

    // The move log replays to the same table.
    $live = app(RoomStates::class)->current($room)->state;
    app(RoomStateStore::class)->forget($room);
    expect(app(RoomStates::class)->rebuild($room)->state)->toBe($live);
});

it('plays at a table with bots, whose chips come from the house', function () {
    $mode = blackjackMode();
    foreach (['Alex', 'Anna'] as $name) {
        BotProfile::factory()->create(['name' => $name])->gameProfiles()->create(['game_id' => $mode->game_id, 'difficulty' => BotDifficulty::Hard]);
    }
    $user = User::factory()->create();
    $balance = app(FishkaWallets::class)->wallet($user)->balance;

    $data = $this->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'hard', 'opponents' => 2])
        ->assertCreated()->json('data');
    $room = blackjackRoom($data['id']);
    expect($room->seats)->toBe(3);

    playBlackjackOut($room, $user);

    $seat = blackjackRoom($room->public_id)->participants->firstWhere('user_id', $user->id);
    expect(app(FishkaWallets::class)->wallet($user)->balance)->toBe($balance + $seat->score);
});

it('lets a player leave with their chips, and that loses the game', function () {
    $mode = blackjackMode();
    $user = User::factory()->create();
    $balance = app(FishkaWallets::class)->wallet($user)->balance;
    $data = $this->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'opponents' => 0])->assertCreated()->json('data');
    $room = blackjackRoom($data['id']);
    $this->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/actions", [
        'client_action_id' => (string) Str::uuid(), 'expected_version' => 0, 'action' => ['type' => 'bet', 'amount' => 1],
    ])->assertOk();

    $this->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/leave")->assertOk();

    $room = blackjackRoom($room->public_id);
    $wallet = app(FishkaWallets::class)->wallet($user)->balance;
    expect($room->status)->toBe(RoomStatus::Finished)
        ->and($room->participants->sole()->result)->toBe(ParticipantResult::Loss)
        // A hand in play is forfeited (1 Fishka at most); a finished hand already paid out.
        ->and($wallet)->toBeGreaterThanOrEqual($balance - 1)
        ->and($wallet)->toBeLessThanOrEqual($balance + 2);
});

it('asks for a difficulty only when bots sit down, and refuses a private table for one', function () {
    $mode = blackjackMode();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'opponents' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('bot_difficulty');
    $this->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'opponents' => 5, 'bot_difficulty' => 'easy'])
        ->assertUnprocessable()->assertJsonValidationErrors('opponents');
    $this->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private', 'seats' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors('seats');
});

it('still requires an opponent in games for two', function () {
    test()->seed([GameSeeder::class, GameModeSeeder::class]);
    $mode = Game::query()->where('slug', 'chess')->sole()->modes()->first();

    $this->actingAs(User::factory()->create())->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'opponents' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('opponents');
});
