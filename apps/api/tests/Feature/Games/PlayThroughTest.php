<?php

use App\Enums\BotDifficulty;
use App\Enums\FishkaTransactionType as Type;
use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Gameplay\Bots\BotTurnJob;
use App\Gameplay\Lifecycle\ResumeRoundJob;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\BotProfile;
use App\Models\FishkaTransaction;
use App\Models\FishkaWallet;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use App\Models\UserGameStat;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

// Every game with an engine is played to the end through the real API and queue jobs.
dataset('games', ['nardi', 'checkers', 'chess', 'durak', 'poker', 'blackjack', 'blot', 'domino']);

it('seeds games as playable only when their engine is registered', function () {
    $this->seed([GameSeeder::class, GameModeSeeder::class]);

    expect(Game::query()->where('is_enabled', true)->pluck('slug')->all())->toBe(['nardi', 'chess', 'checkers', 'durak', 'poker', 'blackjack', 'blot', 'domino']);
});

it('plays a full game against a bot through the API', function (string $slug) {
    Queue::fake();
    // Hundreds of requests in a few seconds: far beyond what a person sends.
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->seed([GameSeeder::class, GameModeSeeder::class]);
    $game = Game::query()->where('slug', $slug)->sole();
    BotProfile::factory()->create(['name' => 'Levon'])->gameProfiles()->create(['game_id' => $game->id, 'difficulty' => BotDifficulty::Medium]);
    $player = User::factory()->create();
    $this->actingAs($player);

    // Tables for more than two: play against two bots there (blot: always four players).
    $opponents = match ($slug) {
        'poker', 'durak', 'blackjack' => ['opponents' => 2],
        'blot' => ['opponents' => 3],
        default => [],
    };
    $id = $this->postJson('/api/v1/rooms', ['game_mode_id' => $game->modes()->first()->id, 'type' => 'bot', 'bot_difficulty' => 'medium', ...$opponents])
        ->assertCreated()
        ->json('data.id');
    $room = GameRoom::query()->where('public_id', $id)->sole();

    for ($i = 0; $i < 4000 && $room->fresh()->status === RoomStatus::Active; $i++) {
        $state = $this->getJson("/api/v1/rooms/{$id}/state")->assertOk()->json('data');

        if ($state['legal_actions'] !== []) {
            $action = $state['legal_actions'][array_rand($state['legal_actions'])];
            // Range actions (poker raises) are described by min and max; play the minimum.
            if (isset($action['min'], $action['max'])) {
                // A blot bid also names its trump suit.
                $action = $slug === 'blot'
                    ? ['type' => $action['type'], 'value' => $action['min'], 'suit' => 'S']
                    : ['type' => $action['type'], 'amount' => $action['min']];
            }
            $this->postJson("/api/v1/rooms/{$id}/actions", [
                'client_action_id' => (string) Str::uuid(),
                'expected_version' => $state['version'],
                'action' => $action,
            ])->assertOk();
        } elseif ($state['seats_to_act'] === []) {
            // A pause between rounds (poker hands): the job that ends it was queued.
            Queue::assertPushed(ResumeRoundJob::class, fn (ResumeRoundJob $job) => $job->version === $state['version']);
            app()->call([new ResumeRoundJob($room->id, $state['version']), 'handle']);
        } else {
            app()->call([new BotTurnJob($room->id, $state['version'], $state['seats_to_act'][0]), 'handle']);
        }
    }

    $room->refresh();
    expect($room->status)->toBe(RoomStatus::Finished)
        ->and($room->end_reason)->toBe('completed')
        ->and(UserGameStat::query()->where('user_id', $player->id)->sole()->played)->toBe(1);

    // Fishkas. Poker: the whole balance as a buy-in, the final chips paid back. Other games: the
    // Beginner bet (500) staked once; the winners share the pot (bet × seats), a draw returns the bet.
    $live = app(RoomStates::class)->current($room->load(['game', 'participants']))->state;
    $me = $room->participants->firstWhere('user_id', $player->id);
    $ledger = FishkaTransaction::query()->where('user_id', $player->id)->orderBy('id')->get();
    $balance = FishkaWallet::query()->findOrFail($player->id)->balance;
    if (in_array($slug, ['poker', 'blackjack'], true)) {
        // Nothing to pay back (no ledger entry) when the player lost every chip.
        $cashOut = $live['stacks'][$me->seat] > 0 ? [Type::PokerWinning] : [];
        expect($ledger->pluck('type')->all())->toBe([Type::RegistrationBonus, Type::PokerBet, ...$cashOut])
            ->and($balance)->toBe($live['stacks'][$me->seat]);
    } else {
        $winners = $room->participants->where('result', ParticipantResult::Win)->count();
        $payout = match ($me->result) {
            ParticipantResult::Win => intdiv(500 * $room->seats, $winners),
            ParticipantResult::Draw => 500,
            default => 0,
        };
        expect($ledger->pluck('type')->all())->toBe([Type::RegistrationBonus, Type::GameStake, ...($payout > 0 ? [Type::GamePayout] : [])])
            ->and($balance)->toBe(4500 + $payout);
    }

    // The move log replays to the same final position.
    app(RoomStateStore::class)->forget($room);
    expect(app(RoomStates::class)->rebuild($room)->state)->toBe($live);
})->with('games');
