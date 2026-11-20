<?php

use App\Models\GameMode;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A game the player finished and got XP for — what GameFinisher records (a finished room, the seat's result, the
 * XP entry referring to the room). Only such games move missions.
 */
function scoringGame(User $user, string $result, ?GameMode $mode = null, ?string $at = null): GameRoom
{
    $mode ??= GameMode::factory()->create();
    $room = GameRoom::query()->create([
        'game_id' => $mode->game_id, 'game_mode_id' => $mode->id, 'type' => 'bot', 'status' => 'finished',
        'created_by' => $user->id, 'seats' => 2, 'has_bot' => true,
    ]);
    (new GameParticipant)->forceFill(['game_room_id' => $room->id, 'user_id' => $user->id, 'seat' => 0, 'result' => $result])->save();
    DB::table('xp_transactions')->insert([
        'user_id' => $user->id, 'amount' => 30, 'source' => 'game', 'reference_type' => 'game_room',
        'reference_id' => $room->public_id, 'level_before' => 1, 'level_after' => 1,
        'idempotency_key' => "game:{$room->public_id}:{$user->id}", 'created_at' => $at ?? now(),
    ]);

    return $room;
}

function missionPlayer(): User
{
    $user = User::factory()->create();
    app(FishkaWallets::class)->open($user);

    return $user;
}

beforeEach(function () {
    // 2026-11-20 is day 324 (even): the first daily set — play 3, win 1, two different games.
    $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00', 'Asia/Yerevan'));
});

it('counts real games toward today’s missions and this week’s challenges', function () {
    $user = missionPlayer();
    $chess = GameMode::factory()->create();
    scoringGame($user, 'win', $chess);
    scoringGame($user, 'loss', $chess);
    scoringGame($user, 'draw');
    // Yesterday's game and someone else's don't count.
    scoringGame($user, 'win', $chess, now()->subDay()->toDateTimeString());
    scoringGame(missionPlayer(), 'win');

    $missions = collect($this->actingAs($user)->getJson('/api/v1/me/missions')->assertOk()->json('data'))->keyBy('key');

    expect($missions->keys()->all())->toBe(['play_3', 'win_1', 'variety_2', 'week_play_20', 'week_win_10'])
        ->and($missions['play_3'])->toMatchArray(['period' => 'day', 'progress' => 3, 'target' => 3, 'claimed' => false])
        ->and($missions['win_1']['progress'])->toBe(1)
        ->and($missions['variety_2']['progress'])->toBe(2)
        ->and($missions['week_play_20']['progress'])->toBe(4) // yesterday is in this week
        ->and($missions['week_win_10']['progress'])->toBe(2);
});

it('pays a finished mission once, and refuses one that isn’t finished', function () {
    $user = missionPlayer();
    scoringGame($user, 'win');

    $this->actingAs($user)->postJson('/api/v1/me/missions/win_1/claim')->assertOk()
        ->assertJsonFragment(['key' => 'win_1', 'claimed' => true]);
    $this->actingAs($user)->postJson('/api/v1/me/missions/win_1/claim')->assertOk();
    $this->actingAs($user)->postJson('/api/v1/me/missions/play_3/claim')->assertStatus(422);
    $this->actingAs($user)->postJson('/api/v1/me/missions/nonsense/claim')->assertStatus(422);

    expect((int) DB::table('fishka_wallets')->where('user_id', $user->id)->value('balance'))->toBe(5000 + 400)
        ->and(DB::table('fishka_transactions')->where('user_id', $user->id)->where('type', 'mission_reward')->count())->toBe(1);
});

it('starts afresh the next day, with the other set of missions', function () {
    $user = missionPlayer();
    scoringGame($user, 'win');
    $this->actingAs($user)->postJson('/api/v1/me/missions/win_1/claim')->assertOk();

    $this->travel(1)->days();
    $missions = collect($this->actingAs($user)->getJson('/api/v1/me/missions')->json('data'))->keyBy('key');

    expect($missions->keys()->take(3)->all())->toBe(['play_5', 'win_2', 'variety_3'])
        ->and($missions['win_2'])->toMatchArray(['progress' => 0, 'claimed' => false])
        ->and($missions['week_win_10']['progress'])->toBe(1);
});
