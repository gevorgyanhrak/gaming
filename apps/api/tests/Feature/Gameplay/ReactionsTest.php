<?php

use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Gameplay\Broadcasting\RoomReactionSent;
use App\Gameplay\Reactions\RoomReactions;
use App\Gameplay\Rooms\RoomService;
use App\Models\GameRoom;
use App\Models\User;
use App\Platform\Settings\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Queue::fake();
    Event::fake([RoomReactionSent::class]);
});

/** A started game for three people; returns [room, alice (seat 0), bob (seat 1), carol (seat 2)]. */
function reactionRoom(): array
{
    [$alice, $bob, $carol] = User::factory()->count(3)->create();
    $room = app(RoomService::class)->createStarted(ticTacToe(['max_players' => 3]), RoomType::Private, [$alice, $bob, $carol]);

    return [$room->fresh(['participants']), $alice, $bob, $carol];
}

function react(GameRoom $room, User $user, array $body = []): TestResponse
{
    return test()->actingAs($user)->postJson("/api/v1/rooms/{$room->public_id}/reactions", [
        'client_id' => (string) Str::uuid(), 'kind' => 'emoji', 'value' => '❤️', 'to_seat' => 1, ...$body,
    ]);
}

it('serves the catalog: twelve emoji categories and the quick messages', function () {
    $data = $this->getJson('/api/v1/reactions')->assertOk()->json('data');

    expect(collect($data['emoji'])->pluck('slug')->all())->toBe([
        'smileys', 'reactions', 'funny', 'love', 'celebration', 'gaming', 'poker', 'victory', 'animals', 'food', 'sports', 'popular',
    ])->and(collect($data['emoji'])->sum(fn ($c) => count($c['emojis'])))->toBeGreaterThan(180)
        ->and(collect($data['messages'])->pluck('keys')->flatten()->all())->toContain('good_game', 'gg', 'rematch', 'well_played');
});

it('delivers an emoji from one player to another to everyone at the table', function () {
    [$room, $alice, $bob, $carol] = reactionRoom();

    $reaction = react($room, $alice)->assertCreated()->json('data');

    expect($reaction)->toMatchArray(['room' => $room->public_id, 'from_seat' => 0, 'to_seat' => 1, 'kind' => 'emoji', 'value' => '❤️', 'seq' => 1]);
    foreach ([$alice, $bob, $carol] as $user) {
        Event::assertDispatched(RoomReactionSent::class, fn (RoomReactionSent $e) => $e->userId === $user->id && $e->reaction['id'] === $reaction['id']);
    }
    Event::assertDispatchedTimes(RoomReactionSent::class, 3);
});

it('sends a quick message back the other way, or to everyone', function () {
    [$room, $alice, $bob] = reactionRoom();

    react($room, $bob, ['kind' => 'message', 'value' => 'good_game', 'to_seat' => 0])->assertCreated()
        ->assertJsonPath('data.from_seat', 1)->assertJsonPath('data.to_seat', 0)->assertJsonPath('data.value', 'good_game');
    react($room, $alice, ['kind' => 'message', 'value' => 'gg', 'to_seat' => null])->assertCreated()
        ->assertJsonPath('data.to_seat', null);
});

it('delivers a repeated request once', function () {
    [$room, $alice] = reactionRoom();
    $id = (string) Str::uuid();

    $first = react($room, $alice, ['client_id' => $id])->assertCreated()->json('data.id');
    $again = react($room, $alice, ['client_id' => $id])->assertCreated()->json('data.id');

    expect($again)->toBe($first);
    Event::assertDispatchedTimes(RoomReactionSent::class, 3);
});

it('keeps the room’s recent reactions in order for pages that catch up', function () {
    [$room, $alice, $bob] = reactionRoom();
    react($room, $alice, ['value' => '😂']);
    react($room, $bob, ['value' => '🔥', 'to_seat' => 0]);
    react($room, $alice, ['kind' => 'message', 'value' => 'haha']);

    $all = $this->actingAs($bob)->getJson("/api/v1/rooms/{$room->public_id}/reactions")->assertOk()->json('data');
    $after = $this->getJson("/api/v1/rooms/{$room->public_id}/reactions?after={$all[0]['seq']}")->assertOk()->json('data');

    expect(collect($all)->pluck('value')->all())->toBe(['😂', '🔥', 'haha'])
        ->and(collect($all)->pluck('seq')->all())->toBe([1, 2, 3])
        ->and(collect($after)->pluck('value')->all())->toBe(['🔥', 'haha']);

    $this->travel(reactionsKeptSeconds() + 1)->seconds();
    expect($this->getJson("/api/v1/rooms/{$room->public_id}/reactions")->json('data'))->toBe([]);
});

function reactionsKeptSeconds(): int
{
    return RoomReactions::KEEP_SECONDS;
}

it('refuses what is not in the catalog, sending to yourself and to an empty seat', function () {
    [$room, $alice] = reactionRoom();

    $cases = [
        'value' => [['value' => 'hello there'], ['kind' => 'message', 'value' => '❤️']],
        'kind' => [['kind' => 'shout']],
        'to_seat' => [['to_seat' => 0], ['to_seat' => 7]],
        'client_id' => [['client_id' => 'x']],
    ];
    foreach ($cases as $field => $bodies) {
        foreach ($bodies as $body) {
            $this->travel(1)->seconds();    // stay under the 3-a-second limit
            react($room, $alice, $body)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
    Event::assertNotDispatched(RoomReactionSent::class);
});

it('only lets the room’s own players react, and only while it is open', function () {
    [$room, $alice] = reactionRoom();

    react($room, User::factory()->create())->assertForbidden();

    $room->forceFill(['status' => RoomStatus::Finished, 'finished_at' => now()->subMinutes(5)])->save();
    react($room, $alice)->assertCreated();
    $room->forceFill(['finished_at' => now()->subMinutes(11)])->save();
    react($room, $alice)->assertStatus(409);
});

it('can be switched off by an administrator', function () {
    [$room, $alice] = reactionRoom();
    app(Settings::class)->update(['reactions.enabled' => false]);

    react($room, $alice)->assertForbidden();
    $this->getJson('/api/v1/config')->assertJsonPath('data.play.reactions', false);
});

it('limits a flood of reactions', function () {
    [$room, $alice] = reactionRoom();

    $statuses = collect(range(1, 5))->map(fn () => react($room, $alice)->status());

    expect($statuses->filter(fn ($s) => $s === 201))->toHaveCount(3)
        ->and($statuses->last())->toBe(429);
});
