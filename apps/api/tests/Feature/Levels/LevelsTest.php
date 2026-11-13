<?php

use App\Enums\BotDifficulty;
use App\Enums\FishkaTransactionType as Type;
use App\Enums\RoomType;
use App\Enums\XpSource;
use App\Filament\Resources\BotProfiles\Pages\EditBotProfile;
use App\Filament\Resources\LevelRewards\Pages\ManageLevelRewards;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Broadcasting\RoomReactionSent;
use App\Gameplay\Engine\Action;
use App\Gameplay\Lifecycle\TurnTimeoutJob;
use App\Gameplay\Reactions\ReactionCatalog;
use App\Gameplay\Rooms\RoomService;
use App\Gameplay\Rooms\RoomStates;
use App\Models\BotProfile;
use App\Models\FishkaTransaction;
use App\Models\GameMode;
use App\Models\GameMove;
use App\Models\GameRoom;
use App\Models\LevelReward;
use App\Models\LevelUp;
use App\Models\User;
use App\Models\XpTransaction;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Levels\LevelItems;
use App\Platform\Levels\Levels;
use App\Platform\Levels\LevelUpdated;
use App\Platform\Settings\Settings;
use Database\Seeders\BotSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
    Event::fake([LevelUpdated::class]);
    // Most tests end a game at once; the minimum moves have tests of their own.
    app(Settings::class)->update(['levels.min_game_moves' => 0]);
});

/** A started tic-tac-toe game between two people; returns [room, alice, bob]. */
function levelRoom(): array
{
    [$alice, $bob] = User::factory()->count(2)->create();
    $mode = GameMode::query()->whereRelation('game', 'slug', 'tictactoe')->first() ?? ticTacToe();
    $room = app(RoomService::class)->createStarted($mode, RoomType::Private, [$alice, $bob]);

    return [$room->fresh(['game', 'participants']), $alice, $bob];
}

/** The player waiting for their turn resigns; returns [winner, loser] users. */
function resignWaiting(GameRoom $room): array
{
    $seat = app(RoomStates::class)->current($room)->seatsToAct[0];
    $waiting = $room->participants->firstWhere('seat', 1 - $seat);
    app(GameActionService::class)->submit($room, $waiting, new Action('resign'));

    return [User::query()->find($room->participants->firstWhere('seat', $seat)->user_id), User::query()->find($waiting->user_id)];
}

function levelStatus(User $user): array
{
    return test()->actingAs($user->fresh())->getJson('/api/v1/me/level')->assertOk()->json('data');
}

it('starts every new player at level 1 with no XP', function () {
    $user = User::factory()->create();

    expect(levelStatus($user))->toMatchArray([
        'level' => 1, 'xp' => 0, 'level_xp' => 0, 'next_level_xp' => 80, 'max_level' => 100, 'pending' => [], 'frame' => null, 'badge' => null,
    ])->and(levelStatus($user)['next_reward'])->toMatchArray(['level' => 2, 'fishkas' => 350, 'is_milestone' => false]);
    $this->actingAs($user)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.level', 1);
});

it('pays XP for a finished game: more for the winner, once per game', function () {
    [$room] = levelRoom();
    $this->travel(60)->seconds();
    [$winner, $loser] = resignWaiting($room);

    expect($winner->fresh()->xp)->toBe(50)->and($loser->fresh()->xp)->toBe(30)
        ->and(XpTransaction::query()->where('source', XpSource::Game)->count())->toBe(2)
        ->and(XpTransaction::query()->where('user_id', $winner->id)->sole()->reference_id)->toBe($room->public_id);

    // The same game paying again (a retried finish) changes nothing.
    app(Levels::class)->award($winner->id, 50, XpSource::Game, "game:{$room->public_id}:{$winner->id}");
    expect($winner->fresh()->xp)->toBe(50)->and(XpTransaction::query()->count())->toBe(2);
    Event::assertDispatched(LevelUpdated::class, fn (LevelUpdated $e) => $e->userId === $winner->id && $e->xp === 50 && $e->gained === 50);
});

it('pays no XP for a game resigned at once, nor to a player who timed out', function () {
    [$quick] = levelRoom();
    resignWaiting($quick);    // under levels.min_game_seconds

    [$room] = levelRoom();
    $seat = app(RoomStates::class)->current($room)->seatsToAct[0];
    $this->travel(60)->seconds();
    (new TurnTimeoutJob($room->id, 0))->handle(app(GameActionService::class));

    $timedOut = $room->participants->firstWhere('seat', $seat)->user_id;
    $winner = $room->participants->firstWhere('seat', 1 - $seat)->user_id;
    expect(XpTransaction::query()->whereIn('user_id', $quick->participants->pluck('user_id'))->count())->toBe(0)
        ->and(XpTransaction::query()->where('user_id', $timedOut)->count())->toBe(0)
        ->and(User::query()->find($winner)->xp)->toBe(50);
});

it('pays no XP to a player who sat a game out and left, but does to one who played', function () {
    app(Settings::class)->update(['levels.min_game_moves' => 1]);

    [$idle] = levelRoom();
    $this->travel(60)->seconds();
    resignWaiting($idle);
    expect(XpTransaction::query()->whereIn('user_id', $idle->participants->pluck('user_id'))->count())->toBe(0);

    [$room] = levelRoom();
    $actions = app(GameActionService::class);
    $states = app(RoomStates::class);
    foreach ([0, 1] as $cell) {
        $seat = $states->current($room->fresh())->seatsToAct[0];
        $actions->submit($room->fresh(['game', 'participants']), $room->participants->firstWhere('seat', $seat), new Action('place', ['cell' => $cell]));
    }
    $this->travel(60)->seconds();
    [$winner, $loser] = resignWaiting($room->fresh(['game', 'participants']));

    expect($winner->fresh()->xp)->toBe(50)->and($loser->fresh()->xp)->toBe(30);
});

it('does not count turns the server played for a player as playing', function () {
    app(Settings::class)->update(['levels.min_game_moves' => 1]);
    [$room] = levelRoom();
    $seat = app(RoomStates::class)->current($room)->seatsToAct[0];
    $idle = $room->participants->firstWhere('seat', 1 - $seat);
    $room->moves()->create(['seq' => 900, 'participant_id' => $idle->id, 'type' => 'check', 'client_action_id' => GameMove::AUTO_PREFIX.'900']);

    $this->travel(60)->seconds();
    resignWaiting($room);

    expect(XpTransaction::query()->where('user_id', $idle->user_id)->count())->toBe(0);
});

it('gives the 100 seeded bots varied levels, the same on every seeding', function () {
    BotProfile::query()->delete();
    $this->seed(BotSeeder::class);

    $levels = BotProfile::query()->pluck('level', 'name');
    expect($levels)->toHaveCount(100)->and($levels->unique()->count())->toBeGreaterThan(40)
        ->and($levels->min())->toBeGreaterThanOrEqual(3)->and($levels->max())->toBeLessThanOrEqual(85)
        ->and($levels['Davo91'])->toBe(BotSeeder::level('Davo91'));
});

it('tells other tabs when the rewards are collected', function () {
    $user = User::factory()->create();
    app(Levels::class)->award($user->id, 100, XpSource::Game, 'tabs-'.$user->id);

    $this->actingAs($user)->postJson('/api/v1/me/level-ups/claim')->assertCreated();

    Event::assertDispatched(LevelUpdated::class, fn (LevelUpdated $e) => $e->userId === $user->id && $e->pending === 0 && $e->gained === 0);
});

it('moves the progress bar and levels up when the XP is reached, with the reward waiting', function () {
    $user = User::factory()->create();
    $levels = app(Levels::class);

    $levels->award($user->id, 60, XpSource::Game, 'test:1');
    expect(levelStatus($user))->toMatchArray(['level' => 1, 'xp' => 60, 'level_xp' => 0, 'next_level_xp' => 80, 'pending' => []]);

    $levels->award($user->id, 30, XpSource::Game, 'test:2');
    $status = levelStatus($user);
    expect($status)->toMatchArray(['level' => 2, 'xp' => 90, 'level_xp' => 80, 'next_level_xp' => 180])
        ->and($status['pending'])->toBe([['level' => 2, 'fishkas' => 350, 'items' => [], 'is_milestone' => false]]);
    Event::assertDispatched(LevelUpdated::class, fn (LevelUpdated $e) => $e->level === 2 && $e->pending === 1);
});

it('handles several level-ups at once, one reward each, milestones included', function () {
    $user = User::factory()->create();
    app(FishkaWallets::class)->open($user);
    $before = app(FishkaWallets::class)->wallet($user)->balance;

    // Total for level 6: 80 + 100 + 120 + 140 + 160.
    app(Levels::class)->award($user->id, 600, XpSource::Game, 'test:big');

    $status = levelStatus($user);
    expect($status['level'])->toBe(6)
        ->and(collect($status['pending'])->pluck('level')->all())->toBe([2, 3, 4, 5, 6])
        ->and(collect($status['pending'])->firstWhere('level', 5))->toBe(['level' => 5, 'fishkas' => 2500, 'items' => [['kind' => 'badge', 'key' => 'rising']], 'is_milestone' => true])
        ->and($status['badge'])->toBe('rising');

    $claim = $this->actingAs($user)->postJson('/api/v1/me/level-ups/claim')->assertCreated()->json('data');
    $sum = 350 + 400 + 450 + 2500 + 550;
    expect($claim['collected'])->toBe($sum)->and($claim['claimed'])->toBe([2, 3, 4, 5, 6])->and($claim['status']['pending'])->toBe([])
        ->and(app(FishkaWallets::class)->wallet($user)->fresh()->balance)->toBe($before + $sum)
        ->and(FishkaTransaction::query()->where('user_id', $user->id)->where('type', Type::LevelReward)->count())->toBe(5);
});

it('grants each reward exactly once, however often it is collected', function () {
    $user = User::factory()->create();
    app(Levels::class)->award($user->id, 80, XpSource::Game, 'test:1');

    $this->actingAs($user)->postJson('/api/v1/me/level-ups/claim')->assertCreated()->assertJsonPath('data.collected', 350);
    $this->actingAs($user)->postJson('/api/v1/me/level-ups/claim')->assertOk()->assertJsonPath('data.collected', 0);
    // Even a level-up row reset by hand pays nothing twice: the payout's key is per level.
    LevelUp::query()->update(['claimed_at' => null]);
    app(Levels::class)->claim($user);

    expect(FishkaTransaction::query()->where('user_id', $user->id)->where('type', Type::LevelReward)->count())->toBe(1)
        ->and(LevelUp::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('keeps a level-up reached while away pending until the player comes back', function () {
    $user = User::factory()->create();
    // XP arrives with no page open (e.g. a game that finished after the player left).
    app(Levels::class)->award($user->id, 200, XpSource::Game, 'test:away');
    $this->travel(3)->days();

    expect(collect(levelStatus($user)['pending'])->pluck('level')->all())->toBe([2, 3]);
});

it('never takes a level away when the curve is made harder', function () {
    $user = User::factory()->create();
    app(Levels::class)->award($user->id, 200, XpSource::Game, 'test:1');
    expect($user->fresh()->level)->toBe(3);

    app(Settings::class)->update(['levels.xp_base' => 1000]);
    app()->forgetScopedInstances();
    app(Levels::class)->award($user->id, 10, XpSource::Game, 'test:2');

    expect($user->fresh()->level)->toBe(3)->and(LevelUp::query()->where('user_id', $user->id)->count())->toBe(2);
});

it('uses the rewards set in the admin panel, and the default for levels without one', function () {
    LevelReward::query()->whereKey(2)->update(['fishkas' => 777, 'items' => json_encode([['kind' => 'frame', 'key' => 'gold'], ['kind' => 'nope', 'key' => 'x']])]);
    $levels = app(Levels::class);

    expect($levels->rewardFor(2))->toBe(['fishkas' => 777, 'items' => [['kind' => 'frame', 'key' => 'gold']], 'is_milestone' => false])
        ->and($levels->rewardFor(31))->toBe(['fishkas' => 3000, 'items' => [], 'is_milestone' => false])
        ->and($levels->looks(1))->toBe(['frame' => null, 'badge' => null])
        ->and($levels->looks(2))->toBe(['frame' => 'gold', 'badge' => null])
        ->and($levels->looks(30))->toBe(['frame' => 'legend', 'badge' => 'champion']);
});

it('gives XP for the daily bonus once a day', function () {
    $this->travelTo(now()->setTimezone('Asia/Yerevan')->setTime(12, 0));
    $user = User::factory()->create();
    app(FishkaWallets::class)->open($user);
    $this->travel(1)->days();

    $this->actingAs($user)->postJson('/api/v1/me/daily-bonus')->assertCreated();
    $this->actingAs($user)->postJson('/api/v1/me/daily-bonus')->assertOk();

    expect($user->fresh()->xp)->toBe(20)
        ->and(XpTransaction::query()->where('user_id', $user->id)->sole()->source)->toBe(XpSource::DailyBonus);
});

it('shows bots with their own high level, and gives them no XP or rewards', function () {
    $bot = BotProfile::factory()->create(['level' => 42]);
    $user = User::factory()->create();
    $room = app(RoomService::class)->createStarted(ticTacToe(), RoomType::Bot, [$user, ['bot' => $bot, 'difficulty' => BotDifficulty::Easy]]);
    $this->travel(60)->seconds();
    $room = $room->fresh(['game', 'participants']);
    app(GameActionService::class)->submit($room, $room->participants->firstWhere('user_id', $user->id), new Action('resign'));

    $participants = collect($this->actingAs($user)->getJson("/api/v1/rooms/{$room->public_id}")->assertOk()->json('data.participants'));
    expect($participants->firstWhere('is_bot', true))->toMatchArray(['level' => 42, 'frame' => null])
        ->and($participants->firstWhere('is_bot', false))->toMatchArray(['level' => 1])
        ->and(XpTransaction::query()->count())->toBe(1)
        ->and(XpTransaction::query()->sole()->user_id)->toBe($user->id)
        ->and(LevelUp::query()->whereNot('user_id', $user->id)->count())->toBe(0);
});

it('keeps existing bots at distinct, fairly high levels', function () {
    $levels = BotProfile::factory()->count(5)->create()->pluck('level');

    expect($levels->min())->toBeGreaterThanOrEqual(18)->and($levels->max())->toBeLessThanOrEqual(57);
});

it('shows a player’s level on their public profile', function () {
    $user = User::factory()->create();
    app(Levels::class)->award($user->id, 80, XpSource::Game, 'test:1');

    $this->getJson("/api/v1/users/{$user->username}")->assertOk()
        ->assertJsonPath('data.level', 2)
        ->assertJsonPath('data.looks', ['frame' => null, 'badge' => null]);
});

it('locks the exclusive emojis until the level that unlocks them', function () {
    Event::fake([LevelUpdated::class, RoomReactionSent::class]);
    [$room, $alice] = levelRoom();
    $crown = LevelItems::EMOJI_PACKS['royal'][0];

    expect($this->getJson('/api/v1/reactions')->json('data.exclusive'))->toContain(['emoji' => $crown, 'level' => 10]);

    $send = fn () => $this->actingAs($alice)->postJson("/api/v1/rooms/{$room->public_id}/reactions", [
        'client_id' => (string) Str::uuid(), 'kind' => 'emoji', 'value' => $crown, 'to_seat' => null,
    ]);
    $send()->assertUnprocessable()->assertJsonValidationErrors(['value' => 'level 10']);

    app(Levels::class)->award($alice->id, app(Levels::class)->curve()->totalFor(10), XpSource::Game, 'test:10');
    $send()->assertCreated();
});

it('keeps the exclusive emojis out of the free catalog', function () {
    $free = collect(app(ReactionCatalog::class)->emojiCategories())->pluck('emojis')->flatten(1)->pluck('emoji');

    expect($free->intersect(collect(LevelItems::EMOJI_PACKS)->flatten()))->toBeEmpty();
});

it('lets administrators edit the rewards and bots’ levels', function () {
    signInToAdmin();

    Livewire\Livewire::test(ManageLevelRewards::class)
        ->assertCanSeeTableRecords(LevelReward::query()->whereIn('level', [2, 3])->get());
    $bot = BotProfile::factory()->create();
    Livewire\Livewire::test(EditBotProfile::class, ['record' => $bot->getRouteKey()])
        ->fillForm(['level' => 77])->call('save')->assertHasNoFormErrors();

    expect($bot->fresh()->level)->toBe(77);
});
