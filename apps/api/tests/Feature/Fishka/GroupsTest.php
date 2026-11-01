<?php

use App\Enums\BotDifficulty;
use App\Enums\FishkaTransactionType as Type;
use App\Enums\ParticipantResult;
use App\Gameplay\Rooms\RoomStakes;
use App\Gameplay\Rooms\RoomStates;
use App\Models\BotProfile;
use App\Models\FishkaTransaction;
use App\Models\Game;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Notification::fake();
    Queue::fake();
    $this->seed([GameSeeder::class, GameModeSeeder::class]);
});

/** A player with exactly $balance Fishkas. */
function withBalance(int $balance): User
{
    $user = User::factory()->create();
    $staff = User::query()->firstWhere('username', 'groups_staff') ?? User::factory()->create(['username' => 'groups_staff']);
    app(FishkaWallets::class)->adjust($user, $staff, 'set', $balance, 'test');

    return $user;
}

function modeOf(string $slug): GameMode
{
    $game = Game::query()->where('slug', $slug)->sole();
    BotProfile::factory()->create()->gameProfiles()->create(['game_id' => $game->id, 'difficulty' => BotDifficulty::Easy]);

    return $game->modes()->first();
}

function startBot(User $user, GameMode $mode, array $extra = []): TestResponse
{
    return test()->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'easy', ...$extra]);
}

it('lists the five groups of each kind on the game page', function () {
    $poker = $this->getJson('/api/v1/games/poker')->assertOk()->json('data.groups');
    $durak = $this->getJson('/api/v1/games/durak')->assertOk()->json('data.groups');

    expect(collect($poker)->map(fn ($g) => [$g['slug'], $g['min_balance'], $g['min_bet']])->all())->toBe([
        ['beginner', 100, 1], ['intermediate', 1000, 5], ['experienced', 10000, 10], ['professional', 50000, 20], ['master', 500000, 100],
    ])->and(collect($durak)->map(fn ($g) => [$g['slug'], $g['min_balance'], $g['min_bet'], $g['max_bet']])->all())->toBe([
        ['beginner', 500, 500, 2000], ['casual', 5000, 2000, 10000], ['experienced', 25000, 10000, 50000],
        ['professional', 100000, 50000, 200000], ['master', 500000, 100000, 500000],
    ]);
});

it('lets players into every group they can afford, and no higher, without charging the minimum', function () {
    $mode = modeOf('checkers');
    $user = withBalance(30_000);

    startBot($user, $mode, ['group' => 'professional'])->assertUnprocessable()
        ->assertJsonValidationErrors(['group' => 'You need at least 100,000 Fishkas to play in this group.']);
    $id = startBot($user, $mode, ['group' => 'experienced', 'bet' => 12_000])->assertCreated()
        ->assertJsonPath('data.group.slug', 'experienced')->assertJsonPath('data.stake', 12_000)->json('data.id');

    // Only the bet is taken; the 25,000 minimum is just the entry check.
    expect(app(FishkaWallets::class)->wallet($user)->refresh()->balance)->toBe(18_000);
    $this->postJson("/api/v1/rooms/{$id}/leave")->assertOk();
});

it('refuses bets outside the group’s range, checked on the server', function () {
    $mode = modeOf('nardi');
    $user = withBalance(100_000);

    startBot($user, $mode, ['group' => 'casual', 'bet' => 1_999])->assertUnprocessable()->assertJsonValidationErrors('bet');
    startBot($user, $mode, ['group' => 'casual', 'bet' => 10_001])->assertUnprocessable()->assertJsonValidationErrors('bet');
    startBot($user, $mode, ['group' => 'nonsense'])->assertUnprocessable()->assertJsonValidationErrors('group');
    expect(app(FishkaWallets::class)->wallet($user)->refresh()->balance)->toBe(100_000);
});

it('plays poker groups with their blinds and refuses groups above the balance', function () {
    $mode = modeOf('poker');
    $user = withBalance(60_000);

    startBot($user, $mode, ['group' => 'master'])->assertUnprocessable()->assertJsonValidationErrors('group');
    $id = startBot($user, $mode, ['group' => 'professional'])->assertCreated()->json('data.id');
    $room = GameRoom::query()->where('public_id', $id)->sole()->load(['game', 'participants']);
    $state = app(RoomStates::class)->current($room)->state;

    expect($room->options)->toMatchArray(['small_blind' => 10, 'big_blind' => 20])
        ->and($state['blinds'])->toBe([10, 20])
        ->and($state['stacks'][$room->participants->firstWhere('user_id', $user->id)->seat] + max($state['committed']))->toBeGreaterThanOrEqual(60_000 - 20);
});

it('gives the pot to the winner of a stake game and nothing to the loser', function () {
    $mode = modeOf('checkers');
    [$alice, $bob] = [withBalance(5_000), withBalance(5_000)];
    $room = $this->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private', 'group' => 'casual', 'bet' => 3_000])->assertCreated()->json('data');
    $this->actingAs($bob)->postJson('/api/v1/rooms/join', ['invite_code' => $room['invite_code']])->assertOk();

    $this->actingAs($bob)->postJson("/api/v1/rooms/{$room['id']}/leave")->assertOk();

    expect(app(FishkaWallets::class)->wallet($alice)->refresh()->balance)->toBe(8_000)
        ->and(app(FishkaWallets::class)->wallet($bob)->refresh()->balance)->toBe(2_000)
        ->and(FishkaTransaction::query()->where('user_id', $alice->id)->where('type', Type::GamePayout)->sole()->amount)->toBe(6_000);
});

it('returns the bets after a draw, once', function () {
    $mode = modeOf('chess');
    [$alice, $bob] = [withBalance(5_000), withBalance(5_000)];
    $id = $this->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private'])->assertCreated()->json('data');
    $this->actingAs($bob)->postJson('/api/v1/rooms/join', ['invite_code' => $id['invite_code']])->assertOk();
    $room = GameRoom::query()->where('public_id', $id['id'])->sole()->load(['game', 'mode', 'participants']);
    $room->participants()->update(['result' => ParticipantResult::Draw]);

    app(RoomStakes::class)->settle($room, []);
    app(RoomStakes::class)->settle($room, []);

    expect(app(FishkaWallets::class)->wallet($alice)->refresh()->balance)->toBe(5_000)
        ->and(app(FishkaWallets::class)->wallet($bob)->refresh()->balance)->toBe(5_000);
});

it('counts chips at a poker table in the wallet, and gives no free bonus while they are in play', function () {
    $mode = modeOf('poker');
    $user = withBalance(300);
    $id = startBot($user, $mode)->assertCreated()->json('data.id');

    $this->actingAs($user)->getJson('/api/v1/me/wallet')
        ->assertOk()->assertJsonPath('data.balance', 0)->assertJsonPath('data.at_table', 300)->assertJsonPath('data.refill.available', false);
    $this->postJson('/api/v1/me/wallet/refill')->assertUnprocessable()->assertJsonPath('code', 'fishka_bonus_unavailable');

    $this->postJson("/api/v1/rooms/{$id}/leave")->assertOk();
    $this->getJson('/api/v1/me/wallet')->assertJsonPath('data.at_table', 0);
});

it('filters the history to one game', function () {
    $mode = modeOf('checkers');
    $user = withBalance(5_000);
    $id = startBot($user, $mode)->assertCreated()->json('data.id');

    $this->actingAs($user)->getJson("/api/v1/me/wallet/transactions?room={$id}")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'game_stake')->assertJsonPath('data.0.reference_id', $id);
});
