<?php

use App\Enums\FishkaTransactionType;
use App\Enums\ParticipantResult;
use App\Enums\RoomType;
use App\Enums\UserStatus;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Engine\Action;
use App\Gameplay\Rooms\RoomService;
use App\Gameplay\Rooms\RoomStates;
use App\Models\BotProfile;
use App\Models\GameMode;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Rankings\Rankings;
use App\Platform\Settings\Settings;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    app(Settings::class)->update(['rankings.points_games' => ['tictactoe']]);
});

/** A started tic-tac-toe game between two new people. */
function rankedRoom(?User $alice = null, ?User $bob = null): GameRoom
{
    $alice ??= User::factory()->create();
    $bob ??= User::factory()->create();
    $mode = GameMode::query()->whereRelation('game', 'slug', 'tictactoe')->first() ?? ticTacToe();

    return app(RoomService::class)->createStarted($mode, RoomType::Private, [$alice, $bob])->fresh(['game', 'participants']);
}

/** The player waiting for their turn resigns; returns [winner, loser]. */
function resignRanked(GameRoom $room): array
{
    $seat = app(RoomStates::class)->current($room)->seatsToAct[0];
    $waiting = $room->participants->firstWhere('seat', 1 - $seat);
    app(GameActionService::class)->submit($room, $waiting, new Action('resign'));

    return [User::query()->find($room->participants->firstWhere('seat', $seat)->user_id), User::query()->find($waiting->user_id)];
}

function pointsOf(User $user): int
{
    return (int) DB::table('game_points')->where('user_id', $user->id)->value('points');
}

it('gives the winner 100 points and takes 50 from the loser, never below 0, once per game', function () {
    [$winner, $loser] = resignRanked(rankedRoom());
    expect(pointsOf($winner))->toBe(100)->and(pointsOf($loser))->toBe(0);

    [$again] = resignRanked($room = rankedRoom($winner, $loser));
    $loserNow = $again->is($winner) ? $loser : $winner;
    expect(pointsOf($again))->toBe($again->is($winner) ? 200 : 100)
        ->and(pointsOf($loserNow))->toBe($again->is($winner) ? 0 : 50);

    // The same result again (a retried finish) changes nothing.
    $before = DB::table('game_points')->sum('points');
    $participant = $room->participants->firstWhere('user_id', $again->id);
    app(Rankings::class)->record($room, $participant, ParticipantResult::Win);
    expect((int) DB::table('game_points')->sum('points'))->toBe((int) $before)
        ->and(DB::table('game_point_events')->count())->toBe(4);
});

it('keeps each game’s points apart, and leaves games without points alone', function () {
    app(Settings::class)->update(['rankings.points_games' => ['chess']]);
    [$winner] = resignRanked(rankedRoom());

    expect(DB::table('game_points')->count())->toBe(0)->and(pointsOf($winner))->toBe(0);
});

it('gives AI players points in their home game, and lists the game’s Top 10 with them marked and their record', function () {
    $room = rankedRoom();
    [$winner] = resignRanked($room);
    $bot = BotProfile::factory()->create(['name' => 'Aram', 'level' => 30, 'home_game_id' => $room->game_id]);
    $ai = (new GameParticipant)->forceFill(['seat' => 7, 'bot_profile_id' => $bot->id, 'user_id' => null]);
    app(Rankings::class)->record($room, $ai, ParticipantResult::Win);
    app(Rankings::class)->record(rankedRoom(), (new GameParticipant)->forceFill(['seat' => 7, 'bot_profile_id' => $bot->id]), ParticipantResult::Win);

    $rows = $this->getJson('/api/v1/rankings/games/tictactoe')->assertOk()->assertJsonPath('kind', 'points')->json('data');
    expect($rows[0])->toMatchArray(['rank' => 1, 'is_ai' => true, 'username' => null, 'name' => 'Aram', 'level' => 30, 'value' => 200, 'played' => 2, 'wins' => 2])
        ->and($rows[1])->toMatchArray(['rank' => 2, 'is_ai' => false, 'username' => $winner->username, 'value' => 100, 'played' => 1, 'wins' => 1])
        ->and($rows)->toHaveCount(2);
});

it('ranks a game by Fishkas when the admin lists it so, and the platform by level and by Fishkas, people only', function () {
    $this->seed(GameSeeder::class);
    app(Settings::class)->update(['rankings.chips_games' => ['poker']]);
    $rich = User::factory()->create(['level' => 3]);
    $high = User::factory()->create(['level' => 40, 'xp' => 99999]);
    $banned = User::factory()->create(['level' => 90, 'status' => UserStatus::Banned]);
    foreach ([$rich, $high, $banned] as $user) {
        app(FishkaWallets::class)->open($user);
    }
    app(FishkaWallets::class)->credit($rich, 50000, FishkaTransactionType::AdminCredit);
    BotProfile::factory()->create(['level' => 99]);

    $this->getJson('/api/v1/rankings/games/poker')->assertOk()
        ->assertJsonPath('kind', 'chips')
        ->assertJsonPath('data.0.username', $rich->username)
        ->assertJsonPath('data.0.value', 55000);
    $this->getJson('/api/v1/rankings/level')->assertOk()
        ->assertJsonPath('data.0.username', $high->username)
        ->assertJsonPath('data.0.value', 40)
        ->assertJsonMissing(['username' => $banned->username])
        ->assertJsonMissing(['is_ai' => true]);
    $this->getJson('/api/v1/rankings/chips')->assertOk()->assertJsonPath('data.0.username', $rich->username);
    $this->getJson('/api/v1/rankings/games/domino')->assertOk()->assertJsonPath('kind', null)->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/rankings/everything')->assertNotFound();
});

it('scores games finished before rankings existed, once however often it runs', function () {
    [$winner] = resignRanked(rankedRoom());
    DB::table('game_point_events')->delete();
    DB::table('game_points')->delete();

    $this->artisan('rankings:backfill')->assertSuccessful();
    $this->artisan('rankings:backfill')->assertSuccessful();

    expect(pointsOf($winner))->toBe(100)->and(DB::table('game_point_events')->count())->toBe(2);
});

it('ranks an AI player only in its home game: points elsewhere never reach that board', function () {
    $room = rankedRoom();
    $away = BotProfile::factory()->create(['name' => 'Away', 'home_game_id' => null]);
    app(Rankings::class)->record($room, (new GameParticipant)->forceFill(['seat' => 7, 'bot_profile_id' => $away->id]), ParticipantResult::Win);

    expect(DB::table('game_points')->where('bot_profile_id', $away->id)->exists())->toBeFalse();
    // Points it held from before still don't show on that board.
    DB::table('game_points')->insert(['game_id' => $room->game_id, 'bot_profile_id' => $away->id, 'points' => 900, 'wins' => 9, 'losses' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $this->getJson('/api/v1/rankings/games/tictactoe')->assertOk()->assertJsonMissing(['name' => 'Away']);
});

it('ranks every game by points by default, Poker and Blackjack too', function () {
    $this->seed(GameSeeder::class);
    // Back to the shipped defaults (beforeEach narrowed the list to tic-tac-toe).
    PlatformSetting::query()->where('key', 'like', 'rankings.%')->delete();
    app(Settings::class)->flush();

    $this->getJson('/api/v1/rankings/games/poker')->assertOk()->assertJsonPath('kind', 'points');
    $this->getJson('/api/v1/rankings/games/blackjack')->assertOk()->assertJsonPath('kind', 'points');
    $this->getJson('/api/v1/rankings/games/domino')->assertOk()->assertJsonPath('kind', 'points');
});
