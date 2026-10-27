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

it('seeds Nardi as playable because its engine is registered', function () {
    $this->seed([GameSeeder::class, GameModeSeeder::class]);

    expect(Game::query()->where('slug', 'nardi')->sole()->is_enabled)->toBeTrue()
        ->and(Game::query()->where('slug', 'chess')->sole()->is_enabled)->toBeFalse();
});

it('plays a full game of Nardi against a bot through the API', function () {
    Queue::fake();
    // Hundreds of requests in a few seconds: far beyond what a person sends.
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->seed([GameSeeder::class, GameModeSeeder::class]);
    $nardi = Game::query()->where('slug', 'nardi')->sole();
    BotProfile::factory()->create(['name' => 'Levon'])->gameProfiles()->create(['game_id' => $nardi->id, 'difficulty' => BotDifficulty::Medium]);
    $player = User::factory()->create();
    $this->actingAs($player);

    $id = $this->postJson('/api/v1/rooms', ['game_mode_id' => $nardi->modes()->first()->id, 'type' => 'bot', 'bot_difficulty' => 'medium'])
        ->assertCreated()
        ->json('data.id');
    $room = GameRoom::query()->where('public_id', $id)->sole();

    for ($i = 0; $i < 1500 && $room->fresh()->status === RoomStatus::Active; $i++) {
        $state = $this->getJson("/api/v1/rooms/{$id}/state")->assertOk()->json('data');

        if ($state['legal_actions'] !== []) {
            $this->postJson("/api/v1/rooms/{$id}/actions", [
                'client_action_id' => (string) Str::uuid(),
                'expected_version' => $state['version'],
                'action' => $state['legal_actions'][array_rand($state['legal_actions'])],
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
});
