<?php

use App\Enums\ParticipantResult;
use App\Enums\RoomType;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Engine\Action;
use App\Gameplay\Lifecycle\TurnTimeoutJob;
use App\Gameplay\Rooms\RoomPresenter;
use App\Gameplay\Rooms\RoomService;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\GameRoom;
use App\Models\User;
use App\Models\UserGameStat;
use Illuminate\Support\Facades\Queue;

/** A tic-tac-toe room with a 60 s + 2 s chess clock. */
function clockRoom(): GameRoom
{
    $mode = ticTacToe(['config' => ['initial_seconds' => 60, 'increment_seconds' => 2]]);
    $room = app(RoomService::class)->createStarted($mode, RoomType::Private, User::factory()->count(2)->create()->all());

    return $room->fresh(['game', 'participants']);
}

function clockMove(GameRoom $room, int $cell): void
{
    $room->load(['game', 'participants']);
    $seat = app(RoomStates::class)->current($room)->seatsToAct[0];
    app(GameActionService::class)->submit($room, $room->participants->firstWhere('seat', $seat), new Action('place', ['cell' => $cell]));
}

function clocksOf(GameRoom $room): ?array
{
    $room->refresh()->load(['game', 'participants']);

    return app(RoomPresenter::class)->stateFor($room, app(RoomStates::class)->current($room), $room->participants->first())['clocks'];
}

beforeEach(function () {
    Queue::fake();
    $this->freezeSecond();
});

it('gives every seat the initial time and the seat to act a deadline from its bank', function () {
    $room = clockRoom();

    expect(clocksOf($room))->toBe([60_000, 60_000])
        ->and(app(RoomStates::class)->current($room)->deadline)->toBe(now()->getTimestamp() + 60);
});

it('charges the time a move took and adds the increment', function () {
    $room = clockRoom();
    $first = app(RoomStates::class)->current($room)->seatsToAct[0];

    $this->travel(10)->seconds();
    clockMove($room, 4);
    $this->travel(5)->seconds();

    $clocks = clocksOf($room);
    expect($clocks[$first])->toBe(52_000)            // 60 - 10 + 2, not running
        ->and($clocks[1 - $first])->toBe(55_000);    // running: 60 - 5
});

it('keeps the clock when the live state is rebuilt from the database', function () {
    $room = clockRoom();
    $this->travel(10)->seconds();
    clockMove($room, 4);

    app(RoomStateStore::class)->forget($room);
    $rebuilt = app(RoomStates::class)->rebuild($room->fresh(['game', 'participants']));

    expect($rebuilt->clock)->toBe($room->fresh()->clock)
        ->and($rebuilt->deadline)->toBe(now()->getTimestamp() + 60);
});

it('ends the game when a clock runs out', function () {
    $room = clockRoom();
    $first = app(RoomStates::class)->current($room)->seatsToAct[0];

    $this->travel(59)->seconds();
    (new TurnTimeoutJob($room->id, 0))->handle(app(GameActionService::class));
    expect($room->fresh()->isActive())->toBeTrue();

    $this->travel(2)->seconds();
    (new TurnTimeoutJob($room->id, 0))->handle(app(GameActionService::class));

    $room->refresh()->load('participants');
    // A loss on time, not a walk-out: it counts in the player's losses.
    expect($room->end_reason)->toBe('timeout')
        ->and($room->participants->firstWhere('seat', $first)->result)->toBe(ParticipantResult::Loss)
        ->and($room->participants->firstWhere('seat', 1 - $first)->result)->toBe(ParticipantResult::Win)
        ->and(UserGameStat::query()->where('user_id', $room->participants->firstWhere('seat', $first)->user_id)->sole())
        ->losses->toBe(1)->abandoned->toBe(0);
});

it('counts a move that arrives after the clock ran out as a loss on time', function () {
    $room = clockRoom();
    $first = app(RoomStates::class)->current($room)->seatsToAct[0];

    $this->travel(60_500)->milliseconds();
    clockMove($room, 4);    // within the lag allowance
    expect($room->fresh()->isActive())->toBeTrue();

    $this->travel(65)->seconds();
    clockMove($room, 0);

    $room->refresh()->load('participants');
    expect($room->end_reason)->toBe('timeout')
        ->and($room->participants->firstWhere('seat', 1 - $first)->result)->toBe(ParticipantResult::Loss);
});

it('has no clocks with per-turn timers', function () {
    $mode = ticTacToe();
    $room = app(RoomService::class)->createStarted($mode, RoomType::Private, User::factory()->count(2)->create()->all());

    expect(clocksOf($room))->toBeNull()
        ->and($room->fresh()->clock)->toBeNull();
});
