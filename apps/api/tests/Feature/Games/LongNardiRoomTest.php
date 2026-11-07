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
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
    $this->seed([GameSeeder::class, GameModeSeeder::class]);
});

it('offers Long and Short nardi, Long first', function () {
    $modes = $this->getJson('/api/v1/games/nardi')->assertOk()->json('data.modes');

    expect(collect($modes)->pluck('slug')->all())->toBe(['long', 'classic'])
        ->and($modes[0]['name'])->toBe('Long nardi')
        ->and($modes[1]['name'])->toBe('Short nardi');
});

it('plays a Long nardi game against two bots through the real pipeline, and the move log replays it', function () {
    $game = Game::query()->where('slug', 'nardi')->sole();
    $long = $game->modes()->where('slug', 'long')->sole();
    // Two bots against each other would need no person; one person who never moves loses on time, so the
    // person plays through their own seat's legal moves and the bot through its jobs.
    BotProfile::factory()->create()->gameProfiles()->create(['game_id' => $game->id, 'difficulty' => BotDifficulty::Medium]);
    $player = User::factory()->create();

    $id = $this->actingAs($player)->postJson('/api/v1/rooms', ['game_mode_id' => $long->id, 'type' => 'bot', 'bot_difficulty' => 'medium'])
        ->assertCreated()->json('data.id');
    $room = GameRoom::query()->where('public_id', $id)->sole();
    $states = app(RoomStates::class);

    $first = $states->current($room->load(['game', 'participants']));
    expect($first->state['variant'])->toBe('long')
        ->and($first->state['points'][23])->toBe(15)
        ->and($first->state['points'][11])->toBe(-15);

    $me = $room->participants->firstWhere('user_id', $player->id)->seat;
    for ($i = 0; $i < 40 && $room->fresh()->isActive(); $i++) {
        $current = $states->current($room->fresh(['game', 'participants']));
        if ($current->seatsToAct === [$me]) {
            $step = $this->getJson("/api/v1/rooms/{$id}/state")->json('data.legal_actions.0');
            $this->postJson("/api/v1/rooms/{$id}/actions", ['client_action_id' => (string) Str::uuid(), 'expected_version' => $current->version, 'action' => $step])->assertOk();
        } else {
            app()->call([new BotTurnJob($room->id, $current->version, $current->seatsToAct[0]), 'handle']);
        }
    }

    $room->refresh()->load(['game', 'participants']);
    $live = $states->current($room)->state;
    expect($room->status)->toBe(RoomStatus::Active)
        ->and($live['bar'])->toBe([0, 0])
        ->and($this->getJson("/api/v1/rooms/{$id}/state")->json('data.view.pips'))->toHaveCount(2);

    app(RoomStateStore::class)->forget($room);
    expect($states->rebuild($room)->state)->toBe($live);
});
