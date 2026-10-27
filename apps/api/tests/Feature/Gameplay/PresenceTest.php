<?php

use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Gameplay\Lifecycle\RoomPresence;
use App\Gameplay\Rooms\RoomService;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\Game;
use App\Models\User;
use App\Platform\Settings\Settings;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Queue;

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

it('ends the game for a player who left the game page, and the other player wins', function () {
    [$room, $alice, $bob] = presenceRoom();

    $this->travel(10)->seconds();
    $this->actingAs($bob)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
    $this->travel(6)->seconds();
    app(RoomPresence::class)->sweep();

    $room->refresh()->load('participants');
    expect($room->status)->toBe(RoomStatus::Finished)
        ->and($room->end_reason)->toBe('abandon')
        ->and($room->participants->firstWhere('user_id', $alice->id)->result)->toBe(ParticipantResult::Abandoned)
        ->and($room->participants->firstWhere('user_id', $bob->id)->result)->toBe(ParticipantResult::Win);
});

it('keeps the game going while both players are on the page', function () {
    [$room, $alice, $bob] = presenceRoom();

    foreach (range(1, 6) as $tick) {
        $this->travel(5)->seconds();
        foreach ([$alice, $bob] as $user) {
            $this->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
        }
        app(RoomPresence::class)->sweep();
    }

    expect($room->fresh()->status)->toBe(RoomStatus::Active);
});

it('never ends games when the platform turns it off', function () {
    app(Settings::class)->update(['matchmaking.abandon_after_seconds' => 0]);
    [$room] = presenceRoom();

    $this->travel(5)->minutes();
    app(RoomPresence::class)->sweep();

    expect($room->fresh()->status)->toBe(RoomStatus::Active);
});

it('takes a leaver out of a bigger table and lets the others play on', function () {
    $this->seed([GameSeeder::class, GameModeSeeder::class]);
    $mode = Game::query()->where('slug', 'poker')->sole()->modes()->sole();
    $players = User::factory()->count(3)->create();
    $room = app(RoomService::class)->createStarted($mode, RoomType::Private, $players->all())->fresh(['game', 'participants']);

    $this->travel(16)->seconds();
    foreach ($players->skip(1) as $user) {
        $this->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertNoContent();
    }
    app(RoomPresence::class)->sweep();

    $room->refresh()->load(['game', 'participants']);
    $leaver = $room->participants->firstWhere('user_id', $players->first()->id);
    expect($room->status)->toBe(RoomStatus::Active)
        ->and($leaver->result)->toBe(ParticipantResult::Abandoned)
        ->and(app(RoomStates::class)->current($room)->state['out'][$leaver->seat])->toBeTrue();

    $live = app(RoomStates::class)->current($room)->state;
    app(RoomStateStore::class)->forget($room);
    expect(app(RoomStates::class)->rebuild($room)->state)->toBe($live);
});

it('accepts presence reports only from the room’s players', function () {
    [$room] = presenceRoom();

    $this->actingAs(User::factory()->create())->postJson("/api/v1/rooms/{$room->public_id}/presence")->assertForbidden();
});

it('tells the SPA how long a player may be away', function () {
    $this->getJson('/api/v1/config')->assertJsonPath('data.play.abandon_after_seconds', 15);
});
