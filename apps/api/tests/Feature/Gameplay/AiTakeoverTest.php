<?php

use App\Enums\BotDifficulty;
use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Bots\AiPlayers;
use App\Gameplay\Bots\BotTurnJob;
use App\Gameplay\GameRegistry;
use App\Gameplay\Lifecycle\ReplaceAwayJob;
use App\Gameplay\Lifecycle\ResumeRoundJob;
use App\Gameplay\Lifecycle\RoomPresence;
use App\Gameplay\Rooms\RoomService;
use App\Gameplay\Rooms\RoomStates;
use App\Models\BotProfile;
use App\Models\Game;
use App\Models\GameMove;
use App\Models\GameRoom;
use App\Models\User;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->freezeSecond();
});

/**
 * A blot table (four seats) of $people people and AI in the other seats, with the deal shown and the bazar open.
 *
 * @return array{0: GameRoom, 1: list<User>}
 */
function takeoverTable(int $people = 2): array
{
    test()->seed([GameSeeder::class, GameModeSeeder::class]);
    $mode = Game::query()->where('slug', 'blot')->sole()->modes()->where('slug', 'bazar')->sole();
    foreach (['Armen', 'Davit', 'Ani'] as $name) {
        BotProfile::factory()->create(['name' => $name])->gameProfiles()->create(['game_id' => $mode->game_id, 'difficulty' => BotDifficulty::Hard]);
    }
    $users = User::factory()->count($people)->create()->all();
    $room = app(RoomService::class)->createStarted($mode, RoomType::Matchmaking, [...$users, ...app(AiPlayers::class)->seats($mode->game, 4 - $people)]);
    (new ResumeRoundJob($room->id, app(RoomStates::class)->current($room->fresh())->version))->handle(app(GameActionService::class));

    return [$room->fresh(['game', 'participants']), $users];
}

/**
 * Everyone but $away keeps reporting; $away's page goes quiet (the sweep opens their return window), and the
 * window ends without them (ADR 0028).
 */
function awayFor(GameRoom $room, array $users, User $away): void
{
    test()->travel(7)->seconds();
    foreach ($users as $user) {
        if ($user->id !== $away->id) {
            test()->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
        }
    }
    app(RoomPresence::class)->sweep();
    test()->travel(5)->seconds();
    $participant = $room->participants()->where('user_id', $away->id)->sole();
    (new ReplaceAwayJob($room->id, $participant->id))->handle(app(RoomPresence::class));
}

it('gives the seat of a player who doesn’t come back to AI for good, and the table plays on', function () {
    [$room, [$alice, $bob]] = takeoverTable();

    awayFor($room, [$alice, $bob], $alice);

    $room->refresh()->load('participants');
    $seat = $room->participants->firstWhere('user_id', $alice->id);
    expect($room->status)->toBe(RoomStatus::Active)
        ->and($seat->ai_controlled)->toBeTrue()
        ->and($seat->replaced_at)->not->toBeNull()
        ->and($seat->result)->toBe(ParticipantResult::Abandoned)
        ->and($room->participants->firstWhere('user_id', $bob->id)->result)->toBeNull();

    // Coming back later doesn't reclaim it.
    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
    expect($seat->fresh()->ai_controlled)->toBeTrue();
});

it('plays a taken-over seat with the game\'s own AI, as the server\'s move (never counted as the player\'s)', function () {
    [$room, [$alice, $bob]] = takeoverTable();
    $toAct = app(RoomStates::class)->current($room)->seatsToAct[0];
    $away = $room->participants->firstWhere('seat', $toAct);
    if ($away->user_id === null) {
        // Let AI play until a person is to act.
        while (($p = $room->participants->firstWhere('seat', app(RoomStates::class)->current($room)->seatsToAct[0]))->user_id === null) {
            (new BotTurnJob($room->id, $room->state_version, $p->seat))->handle(app(RoomStates::class), app(GameRegistry::class), app(GameActionService::class), app(AiPlayers::class));
            $room->refresh()->load('participants');
        }
        $away = $p;
    }
    $user = $away->user_id === $alice->id ? $alice : $bob;

    awayFor($room, [$alice, $bob], $user);
    Queue::assertPushed(BotTurnJob::class, fn (BotTurnJob $job) => $job->seat === $away->seat);

    $room->refresh()->load('participants');
    $version = $room->state_version;
    (new BotTurnJob($room->id, $version, $away->seat))->handle(app(RoomStates::class), app(GameRegistry::class), app(GameActionService::class), app(AiPlayers::class));

    $move = GameMove::query()->where('game_room_id', $room->id)->where('seq', $version + 1)->sole();
    expect($move->participant_id)->toBe($away->id)
        ->and($move->client_action_id)->toStartWith(GameMove::AUTO_PREFIX);
});

it('lets a player leave a table of four: they lose, AI plays their seat on and the game goes on', function () {
    [$room, [$alice, $bob]] = takeoverTable();

    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room->public_id}/leave")->assertSuccessful();

    $room->refresh()->load('participants');
    $seat = $room->participants->firstWhere('user_id', $alice->id);
    expect($room->status)->toBe(RoomStatus::Active)
        ->and($seat->result)->toBe(ParticipantResult::Loss)
        ->and($seat->is_active)->toBeFalse()
        ->and($seat->ai_controlled)->toBeTrue()
        ->and($room->participants->firstWhere('user_id', $bob->id)->result)->toBeNull();

    // Coming back to the page doesn't give a seat back after leaving, nor any move to make.
    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
    expect($seat->fresh()->ai_controlled)->toBeTrue()
        ->and($this->actingAs($alice)->getJson("/api/v1/rooms/{$room->public_id}/state")->json('data.legal_actions'))->toBe([]);
});

it('ends the game when no other person plays at the table (nobody to play on for)', function () {
    [$room, [$alice]] = takeoverTable(people: 1);

    awayFor($room, [$alice], $alice);

    $room->refresh()->load('participants');
    expect($room->status)->toBe(RoomStatus::Finished)
        ->and($room->participants->firstWhere('user_id', $alice->id)->ai_controlled)->toBeFalse();
});

it('lets a game for two go on against AI when one person doesn’t come back (ADR 0028)', function () {
    [$alice, $bob] = User::factory()->count(2)->create();
    $room = app(RoomService::class)->createStarted(ticTacToe(), RoomType::Private, [$alice, $bob])->fresh(['game', 'participants']);

    awayFor($room, [$alice, $bob], $alice);

    $room->refresh()->load('participants');
    expect($room->status)->toBe(RoomStatus::Active)
        ->and($room->participants->firstWhere('user_id', $alice->id)->ai_controlled)->toBeTrue()
        ->and($room->participants->firstWhere('user_id', $alice->id)->result)->toBe(ParticipantResult::Abandoned)
        ->and($room->participants->firstWhere('user_id', $bob->id)->result)->toBeNull();
});

it('never lets a person who lost their seat act as the server for it', function () {
    [$room, [$alice, $bob]] = takeoverTable();
    awayFor($room, [$alice, $bob], $alice);

    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room->public_id}/actions", [
        'client_action_id' => GameMove::AUTO_PREFIX.'forged',
        'expected_version' => $room->fresh()->state_version,
        'action' => ['type' => 'pass'],
    ])->assertStatus(422)->assertJsonValidationErrors('client_action_id');
});
