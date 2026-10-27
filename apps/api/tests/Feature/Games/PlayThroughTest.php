<?php

use App\Enums\BotDifficulty;
use App\Enums\RoomStatus;
use App\Gameplay\Bots\BotTurnJob;
use App\Gameplay\Rooms\RoomStates;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\BotProfile;
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
dataset('games', ['nardi', 'checkers', 'chess', 'durak', 'poker']);

it('seeds games as playable only when their engine is registered', function () {
    $this->seed([GameSeeder::class, GameModeSeeder::class]);

    expect(Game::query()->where('is_enabled', true)->pluck('slug')->all())->toBe(['nardi', 'chess', 'checkers', 'durak', 'poker']);
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

    // Poker and durak tables seat more than two: play against two bots there.
    $opponents = in_array($slug, ['poker', 'durak'], true) ? ['opponents' => 2] : [];
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
                $action = ['type' => $action['type'], 'amount' => $action['min']];
            }
            $this->postJson("/api/v1/rooms/{$id}/actions", [
                'client_action_id' => (string) Str::uuid(),
                'expected_version' => $state['version'],
                'action' => $action,
            ])->assertOk();
        } else {
            app()->call([new BotTurnJob($room->id, $state['version'], $state['seats_to_act'][0]), 'handle']);
        }
    }

    $room->refresh();
    expect($room->status)->toBe(RoomStatus::Finished)
        ->and($room->end_reason)->toBe('completed')
        ->and(UserGameStat::query()->where('user_id', $player->id)->sole()->played)->toBe(1);

    // The move log replays to the same final position.
    $live = app(RoomStates::class)->current($room->load(['game', 'participants']))->state;
    app(RoomStateStore::class)->forget($room);
    expect(app(RoomStates::class)->rebuild($room)->state)->toBe($live);
})->with('games');
