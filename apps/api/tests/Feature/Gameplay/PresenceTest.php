<?php

use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Gameplay\Broadcasting\RoomSeatsChanged;
use App\Gameplay\Lifecycle\ReplaceAwayJob;
use App\Gameplay\Lifecycle\RoomPresence;
use App\Gameplay\Rooms\RoomService;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Queue::fake();
    $this->freezeSecond();
});

function presenceRoom(): array
{
    [$alice, $bob] = User::factory()->count(2)->create();
    $room = app(RoomService::class)->createStarted(ticTacToe(), RoomType::Private, [$alice, $bob]);

    return [$room->fresh(['game', 'participants']), $alice, $bob];
}

/** $user's page goes quiet past the offline limit while the others keep reporting; then the sweep runs. */
function goesQuiet(GameRoom $room, User $user, array $others): void
{
    test()->travel(7)->seconds();
    foreach ($others as $other) {
        test()->actingAs($other)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
    }
    app(RoomPresence::class)->sweep();
}

/** The end of the return window: the job that was queued for it runs. */
function windowEnds(GameRoom $room, User $user): void
{
    $participant = $room->participants()->where('user_id', $user->id)->sole();
    (new ReplaceAwayJob($room->id, $participant->id))->handle(app(RoomPresence::class));
}

it('opens a 5-second return window when a page goes quiet, tells the table, and queues its end', function () {
    Event::fake([RoomSeatsChanged::class]);
    [$room, $alice, $bob] = presenceRoom();

    goesQuiet($room, $alice, [$bob]);

    $seat = $room->participants()->where('user_id', $alice->id)->sole();
    expect($seat->disconnected_at)->not->toBeNull()->and($room->fresh()->status)->toBe(RoomStatus::Active);
    Queue::assertPushed(ReplaceAwayJob::class, fn (ReplaceAwayJob $job) => $job->participantId === $seat->id);
    Event::assertDispatched(RoomSeatsChanged::class, fn ($e) => $e->userId === $bob->id && $e->change === 'disconnected');
    // The others see when the window closes (for the countdown on the seat).
    $seats = collect($this->actingAs($bob)->getJson("/api/v1/rooms/{$room->public_id}")->json('data.participants'))->keyBy('seat');
    expect($seats[$seat->seat]['return_until'])->not->toBeNull()
        ->and($seats[$seat->seat]['replaced'])->toBeFalse();
});

it('keeps the seat of a player who comes back within the window — at 4.9 s too', function () {
    [$room, $alice, $bob] = presenceRoom();
    goesQuiet($room, $alice, [$bob]);

    $this->travel(4900)->milliseconds();
    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
    windowEnds($room, $alice); // the job, arriving afterwards, finds them back

    $seat = $room->participants()->where('user_id', $alice->id)->sole();
    expect($seat->disconnected_at)->toBeNull()->and($seat->ai_controlled)->toBeFalse()->and($seat->is_active)->toBeTrue();
});

it('gives the seat to AI for good at the end of the window: coming back at exactly 5 s is too late', function () {
    [$room, $alice, $bob] = presenceRoom();
    goesQuiet($room, $alice, [$bob]);

    $this->travel(5)->seconds();
    // The return and the deadline arrive together: server time decides, and the boundary counts as over.
    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
    windowEnds($room, $alice);

    $room->refresh()->load('participants');
    $seat = $room->participants->firstWhere('user_id', $alice->id);
    expect($room->status)->toBe(RoomStatus::Active) // a game for two plays on: Bob against AI
        ->and($seat->ai_controlled)->toBeTrue()
        ->and($seat->replaced_at)->not->toBeNull()
        ->and($seat->result)->toBe(ParticipantResult::Abandoned)
        ->and($seat->is_active)->toBeFalse();

    // Later reports change nothing: the seat stays AI's, and Alice has no moves there.
    $this->travel(30)->seconds();
    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
    expect($seat->fresh()->ai_controlled)->toBeTrue()
        ->and($this->actingAs($alice)->getJson("/api/v1/rooms/{$room->public_id}/state")->json('data.legal_actions'))->toBe([]);
});

it('ends a return window at once when the player starts playing at another table', function () {
    [$room, $alice, $bob] = presenceRoom();
    goesQuiet($room, $alice, [$bob]);

    $elsewhere = app(RoomService::class)->createStarted($room->mode, RoomType::Private, [$alice, User::factory()->create()]);
    $this->actingAs($alice)->postJson("/api/v1/rooms/{$elsewhere->public_id}/presence")->assertNoContent();

    $seat = $room->participants()->where('user_id', $alice->id)->sole();
    expect($seat->replaced_at)->not->toBeNull()->and($seat->ai_controlled)->toBeTrue();
});

it('opens the window at once when the page closes (the beacon), not after the silence', function () {
    [$room, $alice] = presenceRoom();

    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room->public_id}/away")->assertNoContent();

    expect($room->participants()->where('user_id', $alice->id)->sole()->disconnected_at)->not->toBeNull();
    Queue::assertPushed(ReplaceAwayJob::class);
});

it('keeps the game going while both players are on the page', function () {
    [$room, $alice, $bob] = presenceRoom();

    $this->travel(4)->seconds();
    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
    $this->actingAs($bob)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
    app(RoomPresence::class)->sweep();

    expect($room->participants()->whereNotNull('disconnected_at')->exists())->toBeFalse();
});

it('at a chip table takes the player out with their chips when the window ends, and the table plays on', function () {
    $this->seed([GameSeeder::class, GameModeSeeder::class]);
    $mode = Game::query()->where('slug', 'poker')->sole()->modes()->sole();
    $players = User::factory()->count(3)->create();
    $room = app(RoomService::class)->createStarted($mode, RoomType::Private, $players->all())->fresh(['game', 'participants']);

    goesQuiet($room, $players->first(), $players->skip(1)->all());
    $this->travel(5)->seconds();
    windowEnds($room, $players->first());

    $room->refresh()->load(['game', 'participants']);
    $leaver = $room->participants->firstWhere('user_id', $players->first()->id);
    expect($room->status)->toBe(RoomStatus::Active)
        ->and($leaver->result)->toBe(ParticipantResult::Abandoned)
        ->and($leaver->replaced_at)->not->toBeNull()
        ->and(app(RoomStates::class)->current($room)->state['out'][$leaver->seat])->toBeTrue();

    $live = app(RoomStates::class)->current($room)->state;
    app(RoomStateStore::class)->forget($room);
    expect(app(RoomStates::class)->rebuild($room)->state)->toBe($live);
});

it('accepts presence reports only from the room’s players', function () {
    [$room] = presenceRoom();

    $this->actingAs(User::factory()->create())->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertForbidden();
});

it('tells the SPA how long a disconnected player has to come back', function () {
    $this->getJson('/api/v1/config')->assertJsonPath('data.play.return_seconds', 5);
});

it('takes presence reports even when the player has used up the game limit', function () {
    [$room, $alice] = presenceRoom();
    $key = md5('game'.$alice->id);
    foreach (range(1, 240) as $hit) {
        RateLimiter::hit($key);
    }

    $this->actingAs($alice)->getJson("/api/v1/rooms/{$room->public_id}/state")->assertTooManyRequests();
    $this->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
});
