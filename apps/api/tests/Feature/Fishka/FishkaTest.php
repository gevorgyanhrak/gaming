<?php

use App\Enums\BotDifficulty;
use App\Enums\FishkaTransactionType as Type;
use App\Enums\RoomStatus;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Gameplay\Rooms\RoomStates;
use App\Models\AuditLog;
use App\Models\BotProfile;
use App\Models\FishkaTransaction;
use App\Models\Game;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Fishka\InsufficientFishkas;
use App\Platform\Moderation\UserModeration;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    Queue::fake();
});

/** A player whose wallet holds exactly $balance Fishkas. */
function playerWith(int $balance): User
{
    $user = User::factory()->create();
    $wallets = app(FishkaWallets::class);
    $wallets->wallet($user);
    $wallets->adjust($user, staff(), 'set', $balance, 'test setup');

    return $user;
}

/** The administrator behind test balance changes (not signed in). */
function staff(): User
{
    return User::query()->firstWhere('username', 'fishka_staff') ?? User::factory()->create(['username' => 'fishka_staff']);
}

function balanceOf(User $user): int
{
    return app(FishkaWallets::class)->wallet($user)->refresh()->balance;
}

/** The mode of $slug, with one bot so bot games can start. */
function fishkaMode(string $slug): GameMode
{
    test()->seed([GameSeeder::class, GameModeSeeder::class]);
    $game = Game::query()->where('slug', $slug)->sole();
    BotProfile::factory()->create()->gameProfiles()->create(['game_id' => $game->id, 'difficulty' => BotDifficulty::Easy]);

    return $game->modes()->first();
}

function botGame(User $user, GameMode $mode): TestResponse
{
    return test()->actingAs($user)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'bot', 'bot_difficulty' => 'easy']);
}

it('gives 5,000 Fishkas once when an account is created, and no daily bonus that day', function () {
    $this->postJson('/api/v1/auth/register', [
        'method' => 'email', 'email' => 'new@example.com', 'username' => 'newbie',
        'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
    ])->assertCreated();
    $user = User::query()->where('username', 'newbie')->sole();

    app(FishkaWallets::class)->open($user);
    // (201 only because the test reuses the model created by the registration request.)
    $this->getJson('/api/v1/me')->assertSuccessful();

    expect(balanceOf($user))->toBe(5000)
        ->and($user->fishkaTransactions()->pluck('type')->all())->toBe([Type::RegistrationBonus]);
});

it('gives the daily bonus on the first sign-in of each day only', function () {
    $this->travelTo(now()->setTimezone('Asia/Yerevan')->setTime(10, 0));
    $user = User::factory()->create(['email' => 'daily@example.com']);
    app(FishkaWallets::class)->open($user);
    $login = fn () => $this->postJson('/api/v1/auth/login', ['login' => 'daily@example.com', 'password' => 'password1'])->assertOk();

    $login();
    $login();
    expect(balanceOf($user))->toBe(5000);

    $this->travel(1)->days();
    $login();
    $login();
    $this->getJson('/api/v1/me')->assertOk();

    expect(balanceOf($user))->toBe(6000)
        ->and($user->fishkaTransactions()->where('type', Type::DailyLoginBonus)->count())->toBe(1);
});

it('counts days in the platform time zone', function () {
    // 21:00 UTC is already the next day in Yerevan (UTC+4).
    $this->travelTo(now()->utc()->setTime(19, 0));
    $user = User::factory()->create();
    app(FishkaWallets::class)->open($user);

    $this->travelTo(now()->utc()->setTime(21, 0));
    $this->actingAs($user)->getJson('/api/v1/me')->assertOk();

    expect(balanceOf($user))->toBe(6000);
});

it('charges the entry fee once per game, and refuses a player who cannot pay', function () {
    $mode = fishkaMode('checkers');
    $rich = playerWith(500);
    $poor = playerWith(499);

    $room = botGame($rich, $mode)->assertCreated()->json('data.id');
    expect(balanceOf($rich))->toBe(0)
        ->and(FishkaTransaction::query()->where('user_id', $rich->id)->where('type', Type::GameEntryFee)->sole())
        ->amount->toBe(-500)
        ->reference_id->toBe($room);

    botGame($poor, $mode)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'insufficient_fishkas')
        ->assertJsonPath('balance', 499)
        ->assertJsonPath('needed', 500);
    expect(balanceOf($poor))->toBe(499)
        ->and(GameRoom::query()->whereHas('participants', fn ($q) => $q->where('user_id', $poor->id))->exists())->toBeFalse();
});

it('refuses a player with no Fishkas everywhere a game starts', function () {
    $mode = fishkaMode('checkers');
    $broke = playerWith(0);

    botGame($broke, $mode)->assertUnprocessable()->assertJsonPath('code', 'insufficient_fishkas');
    $this->actingAs($broke)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private'])
        ->assertUnprocessable()->assertJsonPath('code', 'insufficient_fishkas');
    $this->actingAs($broke)->postJson('/api/v1/matchmaking', ['game_mode_id' => $mode->id])
        ->assertUnprocessable()->assertJsonPath('code', 'insufficient_fishkas');
});

it('gives the fee back when a waiting room is left or closed', function () {
    $mode = fishkaMode('poker');
    $host = playerWith(800);
    $guest = playerWith(2000);
    $room = $this->actingAs($host)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private', 'seats' => 3])->assertCreated()->json('data');
    $this->actingAs($guest)->postJson('/api/v1/rooms/join', ['invite_code' => $room['invite_code']])->assertOk();

    // Poker takes a buy-in: the whole balance up to the table's stack.
    expect(balanceOf($host))->toBe(0)->and(balanceOf($guest))->toBe(1000);

    $this->actingAs($guest)->postJson("/api/v1/rooms/{$room['id']}/leave")->assertOk();
    $this->actingAs($host)->postJson("/api/v1/rooms/{$room['id']}/leave")->assertOk();

    expect(balanceOf($host))->toBe(800)
        ->and(balanceOf($guest))->toBe(2000)
        ->and(FishkaTransaction::query()->where('type', Type::GameEntryRefund)->count())->toBe(2);
});

it('gives the out-of-Fishkas bonus only when needed, and not twice within the cooldown', function () {
    $user = playerWith(0);
    $refill = fn () => $this->actingAs($user)->postJson('/api/v1/me/wallet/refill');

    $refill()->assertCreated()->assertJsonPath('data.balance', 5000)->assertJsonPath('data.refill.available', false);
    // Still enough to play: no bonus.
    $refill()->assertUnprocessable()->assertJsonPath('code', 'fishka_bonus_unavailable')->assertJsonPath('available_at', null);

    app(FishkaWallets::class)->adjust($user, staff(), 'set', 0, 'spent');
    $response = $refill()->assertUnprocessable()->assertJsonPath('code', 'fishka_bonus_unavailable');
    expect($response->json('available_at'))->not->toBeNull();

    $this->travel(25)->hours();
    $refill()->assertCreated()->assertJsonPath('data.balance', 5000);
    expect($user->fishkaTransactions()->where('type', Type::InsufficientBalanceBonus)->count())->toBe(2);
});

it('seats a poker player with as little as 1 Fishka, without the entry fee', function () {
    $mode = fishkaMode('poker');
    $user = playerWith(1);

    $id = botGame($user, $mode)->assertCreated()->json('data.id');
    $room = GameRoom::query()->where('public_id', $id)->sole();
    $me = $room->participants()->whereNotNull('user_id')->sole();

    expect($me->stake)->toBe(1)
        ->and($room->options['stacks'][$me->seat])->toBe(1)
        ->and(FishkaTransaction::query()->where('user_id', $user->id)->where('type', Type::PokerBet)->sole()->amount)->toBe(-1)
        ->and(FishkaTransaction::query()->where('user_id', $user->id)->where('type', Type::GameEntryFee)->exists())->toBeFalse();
});

it('pays the chips back when a poker player leaves the table', function () {
    $mode = fishkaMode('poker');
    $user = playerWith(300);

    $id = botGame($user, $mode)->assertCreated()->json('data.id');
    expect(balanceOf($user))->toBe(0);
    $room = GameRoom::query()->where('public_id', $id)->sole()->load(['game', 'participants']);
    $seat = $room->participants->firstWhere('user_id', $user->id)->seat;
    $state = app(RoomStates::class)->current($room)->state;

    // Leaving pays back the stack; a blind already in the pot stays there, as for anyone who leaves.
    $this->actingAs($user)->postJson("/api/v1/rooms/{$id}/leave")->assertOk();

    expect(GameRoom::query()->where('public_id', $id)->sole()->status)->toBe(RoomStatus::Finished)
        ->and(balanceOf($user))->toBe($state['stacks'][$seat])
        ->and(FishkaTransaction::query()->where('user_id', $user->id)->where('type', Type::PokerWinning)->sole()->amount)->toBe($state['stacks'][$seat]);
});

it('gives the pot to the other player when someone resigns a heads-up poker hand', function () {
    $mode = fishkaMode('poker');
    [$alice, $bob] = [playerWith(5000), playerWith(5000)];
    $room = $this->actingAs($alice)->postJson('/api/v1/rooms', ['game_mode_id' => $mode->id, 'type' => 'private', 'seats' => 2])->assertCreated()->json('data');
    $this->actingAs($bob)->postJson('/api/v1/rooms/join', ['invite_code' => $room['invite_code']])->assertOk();
    $game = GameRoom::query()->where('public_id', $room['id'])->sole()->load(['game', 'participants']);
    $state = app(RoomStates::class)->current($game)->state;
    $aliceSeat = $game->participants->firstWhere('user_id', $alice->id)->seat;
    $inPot = $state['committed'][$aliceSeat];

    $this->actingAs($alice)->postJson("/api/v1/rooms/{$room['id']}/leave")->assertOk();

    expect(balanceOf($alice))->toBe(5000 - $inPot)
        ->and(balanceOf($bob))->toBe(5000 + $inPot)
        ->and(balanceOf($alice) + balanceOf($bob))->toBe(10000);
});

it('never lets a buy-in exceed the balance or the table stack', function () {
    $mode = fishkaMode('poker');
    $user = playerWith(3500);

    $id = botGame($user, $mode)->assertCreated()->json('data.id');
    $me = GameRoom::query()->where('public_id', $id)->sole()->participants()->whereNotNull('user_id')->sole();

    expect($me->stake)->toBe(1000)->and(balanceOf($user))->toBe(2500);
});

it('lets administrators add, deduct and set Fishkas with a reason, audited', function () {
    $admin = signInToAdmin('moderator');
    $player = User::factory()->create();
    app(FishkaWallets::class)->open($player);
    $page = fn () => Livewire::test(ViewUser::class, ['record' => $player->getRouteKey()]);

    $page()->callAction('addFishkas', data: ['amount' => 250, 'reason' => 'Tournament prize'])->assertNotified();
    $page()->callAction('deductFishkas', data: ['amount' => 1250, 'reason' => 'Abuse'])->assertNotified();
    $page()->callAction('setFishkas', data: ['amount' => 42, 'reason' => 'Reset'])->assertNotified();
    $page()->callAction('deductFishkas', data: ['amount' => 43, 'reason' => 'Too much'])->assertNotified();

    $entries = FishkaTransaction::query()->where('user_id', $player->id)->whereNotNull('actor_id')->orderBy('id')->get();
    expect(balanceOf($player))->toBe(42)
        ->and($entries->map->only(['type', 'amount', 'balance_before', 'balance_after', 'reason', 'actor_id'])->all())->toBe([
            ['type' => Type::AdminCredit, 'amount' => 250, 'balance_before' => 5000, 'balance_after' => 5250, 'reason' => 'Tournament prize', 'actor_id' => $admin->id],
            ['type' => Type::AdminDebit, 'amount' => -1250, 'balance_before' => 5250, 'balance_after' => 4000, 'reason' => 'Abuse', 'actor_id' => $admin->id],
            ['type' => Type::AdminDebit, 'amount' => -3958, 'balance_before' => 4000, 'balance_after' => 42, 'reason' => 'Reset', 'actor_id' => $admin->id],
        ])
        ->and(AuditLog::query()->where('action', 'fishka.adjusted')->count())->toBe(3);
});

it('keeps blocked players out of games', function () {
    $mode = fishkaMode('checkers');
    $player = playerWith(5000);
    $moderator = signInToAdmin('moderator', ['username' => 'mod']);
    app(UserModeration::class)->ban($player, $moderator, 'Cheating');

    // A banned account is signed out: no game API answers it, and signing in says why.
    botGame($player->refresh(), $mode)->assertUnauthorized();
    expect(balanceOf($player))->toBe(5000);

    auth('web')->logout();
    $this->postJson('/api/v1/auth/login', ['login' => $player->email, 'password' => 'password1'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.login.0', 'Your account has been blocked. Contact support if you think this is a mistake.');
});

it('lists the ledger newest first with the wallet totals', function () {
    $mode = fishkaMode('checkers');
    $user = playerWith(5000);
    botGame($user, $mode)->assertCreated();

    $this->actingAs($user)->getJson('/api/v1/me/wallet')
        ->assertOk()
        ->assertJsonPath('data.balance', 4500)
        ->assertJsonPath('data.entry_fee', 500)
        ->assertJsonPath('data.refill.available', false);
    $this->actingAs($user)->getJson('/api/v1/me/wallet/transactions')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'game_entry_fee')
        ->assertJsonPath('data.0.amount', -500)
        ->assertJsonPath('data.0.balance_after', 4500)
        ->assertJsonPath('data.1.type', 'registration_bonus');
});

it('never charges twice for the same seat and never goes below zero', function () {
    $user = playerWith(600);
    $wallets = app(FishkaWallets::class);

    $first = $wallets->charge($user, 500, Type::GameEntryFee, ['idempotency_key' => 'stake:test']);
    $again = $wallets->charge($user, 500, Type::GameEntryFee, ['idempotency_key' => 'stake:test']);

    expect($again->id)->toBe($first->id)->and(balanceOf($user))->toBe(100);
    expect(fn () => $wallets->charge($user, 101, Type::GameEntryFee))->toThrow(InsufficientFishkas::class);
    expect(balanceOf($user))->toBe(100);
});
