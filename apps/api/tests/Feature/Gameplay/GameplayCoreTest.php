<?php

use App\Enums\BotDifficulty;
use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Bots\BotTurnJob;
use App\Gameplay\Engine\Action;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Exceptions\RoomNotActive;
use App\Gameplay\Exceptions\VersionConflict;
use App\Gameplay\Lifecycle\TurnTimeoutJob;
use App\Gameplay\Rooms\RoomService;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\BotProfile;
use App\Models\GameRoom;
use App\Models\User;
use App\Models\UserGameStat;
use Illuminate\Support\Facades\Queue;

function humanRoom(): array
{
    $mode = ticTacToe();
    [$alice, $bob] = User::factory()->count(2)->create();
    $room = app(RoomService::class)->createStarted($mode, RoomType::Private, [$alice, $bob]);

    return [$room->fresh(['game', 'participants']), $alice, $bob];
}

/** The participant whose turn it is, and the other one. */
function turnOrder(GameRoom $room): array
{
    $seat = app(RoomStates::class)->current($room)->seatsToAct[0];

    return [$room->participants->firstWhere('seat', $seat), $room->participants->firstWhere('seat', 1 - $seat)];
}

function play(GameRoom $room, int $cell, ?string $clientId = null): void
{
    [$current] = turnOrder($room->fresh(['game', 'participants']));
    app(GameActionService::class)->submit($room, $current, new Action('place', ['cell' => $cell]), clientActionId: $clientId);
}

beforeEach(fn () => Queue::fake());

it('starts a game: active room, start move, live state for the first seat', function () {
    [$room] = humanRoom();

    $state = app(RoomStates::class)->current($room);
    expect($room->status)->toBe(RoomStatus::Active)
        ->and($room->moves()->sole()->seq)->toBe(0)
        ->and($state->version)->toBe(0)
        ->and($state->seatsToAct)->toHaveCount(1)
        ->and($state->deadline)->toBeGreaterThan(now()->getTimestamp());

    Queue::assertPushed(TurnTimeoutJob::class, fn ($job) => $job->roomId === $room->id && $job->version === 0);
});

it('accepts a legal move, logs it and advances the version', function () {
    [$room] = humanRoom();
    [$first] = turnOrder($room);

    $result = app(GameActionService::class)->submit($room, $first, new Action('place', ['cell' => 4]), expectedVersion: 0, clientActionId: 'b3c9f0de-7e9c-4b5e-9f3c-2f1d1a2b3c4d');

    expect($result->version)->toBe(1)
        ->and($room->fresh()->state_version)->toBe(1)
        // toEqual: MySQL's JSON type does not preserve key order.
        ->and($room->moves()->where('seq', 1)->sole()->only(['type', 'payload', 'participant_id']))
        ->toEqual(['type' => 'place', 'payload' => ['type' => 'place', 'cell' => 4], 'participant_id' => $first->id])
        ->and(app(RoomStates::class)->current($room->fresh())->state['board'][4])->toBe($first->seat);
});

it('rejects moves out of turn, illegal moves and stale versions', function () {
    [$room] = humanRoom();
    [$first, $second] = turnOrder($room);
    $actions = app(GameActionService::class);

    expect(fn () => $actions->submit($room, $second, new Action('place', ['cell' => 0])))->toThrow(IllegalAction::class)
        ->and(fn () => $actions->submit($room, $first, new Action('place', ['cell' => 9])))->toThrow(IllegalAction::class)
        ->and(fn () => $actions->submit($room, $first, new Action('teleport')))->toThrow(IllegalAction::class);

    $actions->submit($room, $first, new Action('place', ['cell' => 0]));

    expect(fn () => $actions->submit($room->fresh(), $second, new Action('place', ['cell' => 1]), expectedVersion: 0))
        ->toThrow(fn (VersionConflict $e) => expect($e->currentVersion)->toBe(1))
        ->and($room->moves()->count())->toBe(2);
});

it('applies a retried action only once', function () {
    [$room] = humanRoom();
    [$first] = turnOrder($room);
    $id = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';

    app(GameActionService::class)->submit($room, $first, new Action('place', ['cell' => 4]), clientActionId: $id);
    $retry = app(GameActionService::class)->submit($room, $first, new Action('place', ['cell' => 4]), clientActionId: $id);

    expect($retry->replayed)->toBeTrue()
        ->and($retry->version)->toBe(1)
        ->and($room->moves()->count())->toBe(2);
});

it('finishes the game with results and statistics', function () {
    [$room] = humanRoom();
    [$first, $second] = turnOrder($room);

    // First player takes the top row: 0, 1, 2 (second plays 3, 4).
    foreach ([0, 3, 1, 4, 2] as $cell) {
        play($room, $cell);
    }

    $room->refresh()->load('participants');
    expect($room->status)->toBe(RoomStatus::Finished)
        ->and($room->end_reason)->toBe('completed')
        ->and($room->participants->firstWhere('seat', $first->seat)->result)->toBe(ParticipantResult::Win)
        ->and($room->participants->firstWhere('seat', $second->seat)->result)->toBe(ParticipantResult::Loss)
        ->and($room->participants->every(fn ($p) => ! $p->is_active))->toBeTrue();

    $winnerStats = UserGameStat::query()->where('user_id', $first->user_id)->sole();
    expect([$winnerStats->played, $winnerStats->wins, $winnerStats->current_streak])->toBe([1, 1, 1]);

    expect(fn () => app(GameActionService::class)->submit($room, $second, new Action('place', ['cell' => 5])))
        ->toThrow(RoomNotActive::class);
});

it('records draws', function () {
    [$room] = humanRoom();

    // X O X / X O O / O X X  (first player is X)
    foreach ([0, 1, 2, 4, 3, 5, 7, 6, 8] as $cell) {
        play($room, $cell);
    }

    expect($room->fresh()->participants->pluck('result')->unique()->all())->toBe([ParticipantResult::Draw])
        ->and((int) UserGameStat::query()->sum('draws'))->toBe(2);
});

it('lets a player resign', function () {
    [$room] = humanRoom();
    [$first, $second] = turnOrder($room);

    app(GameActionService::class)->submit($room, $first, new Action('resign'));

    $room->refresh()->load('participants');
    expect($room->end_reason)->toBe('resign')
        ->and($room->participants->firstWhere('seat', $second->seat)->result)->toBe(ParticipantResult::Win);
});

it('ends the game when the turn times out, but not before', function () {
    [$room] = humanRoom();
    [$first, $second] = turnOrder($room);

    (new TurnTimeoutJob($room->id, 0))->handle(app(GameActionService::class));
    expect($room->fresh()->isActive())->toBeTrue();

    $this->travel(31)->seconds();
    (new TurnTimeoutJob($room->id, 0))->handle(app(GameActionService::class));

    $room->refresh()->load('participants');
    expect($room->end_reason)->toBe('timeout')
        ->and($room->participants->firstWhere('seat', $first->seat)->result)->toBe(ParticipantResult::Abandoned)
        ->and($room->participants->firstWhere('seat', $second->seat)->result)->toBe(ParticipantResult::Win)
        ->and(UserGameStat::query()->where('user_id', $first->user_id)->sole()->abandoned)->toBe(1);
});

it('ignores a timeout scheduled for an older version', function () {
    [$room] = humanRoom();
    play($room, 4);

    $this->travel(31)->seconds();
    (new TurnTimeoutJob($room->id, 0))->handle(app(GameActionService::class));

    expect($room->fresh()->isActive())->toBeTrue();
});

it('rebuilds the live state from the move log, including recorded randomness', function () {
    [$room] = humanRoom();
    foreach ([4, 0, 8] as $cell) {
        play($room, $cell);
    }
    $before = app(RoomStates::class)->current($room->fresh());

    app(RoomStateStore::class)->forget($room);
    $after = app(RoomStates::class)->current($room->fresh(['game', 'participants']));

    expect($after->state)->toBe($before->state)
        ->and($after->version)->toBe(3)
        ->and($after->seatsToAct)->toBe($before->seatsToAct);
});

it('lets bots play complete games through the same pipeline', function () {
    $mode = ticTacToe();
    [$a, $b] = BotProfile::factory()->count(2)->create();
    $room = app(RoomService::class)->createStarted($mode, RoomType::Bot, [
        ['bot' => $a, 'difficulty' => BotDifficulty::Easy],
        ['bot' => $b, 'difficulty' => BotDifficulty::Hard],
    ]);

    Queue::assertPushed(BotTurnJob::class, fn ($job) => $job->roomId === $room->id && $job->version === 0);

    // Run each scheduled bot turn, as the queue worker would.
    for ($i = 0; $i < 12 && $room->fresh()->isActive(); $i++) {
        $state = app(RoomStates::class)->current($room->fresh(['game', 'participants']));
        app()->call([new BotTurnJob($room->id, $state->version, $state->seatsToAct[0]), 'handle']);
    }

    $room->refresh();
    expect($room->status)->toBe(RoomStatus::Finished)
        ->and($room->has_bot)->toBeTrue()
        ->and($room->moves()->count())->toBeGreaterThanOrEqual(6)
        ->and(UserGameStat::query()->count())->toBe(0);
});
