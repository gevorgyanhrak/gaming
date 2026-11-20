<?php

use App\Gameplay\Arena\AiArena;
use App\Models\BotProfile;
use App\Models\Game;
use Database\Seeders\BotSeeder;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed([GameSeeder::class, GameModeSeeder::class, BotSeeder::class]);
});

it('gives every AI player one home game, spread evenly, so no AI can be on two boards', function () {
    $homes = DB::table('bot_profiles')->selectRaw('home_game_id, count(*) as c')->groupBy('home_game_id')->pluck('c', 'home_game_id');

    expect($homes->keys()->contains(null))->toBeFalse()
        ->and($homes->max() - $homes->min())->toBeLessThanOrEqual(1)
        ->and($homes->min())->toBeGreaterThanOrEqual(10);
    // Each bot plays its home game.
    foreach (BotProfile::query()->get() as $bot) {
        expect($bot->gameProfiles()->where('game_id', $bot->home_game_id)->exists())->toBeTrue();
    }
});

it('plays a whole real game between home AI players and scores each seat once, for that game only', function () {
    $durak = Game::query()->where('slug', 'durak')->firstOrFail();

    $played = app(AiArena::class)->play($durak);

    expect($played)->not->toBeNull();
    $row = DB::table('ai_arena_games')->where('public_id', $played['public_id'])->first();
    expect($row->game_id)->toBe($durak->id)->and($row->moves)->toBeGreaterThan(0);
    foreach ($played['bots'] as $botId) {
        expect(BotProfile::query()->find($botId)->home_game_id)->toBe($durak->id);
    }
    $events = DB::table('game_point_events')->where('room_public_id', $played['public_id'])->get();
    // A draw (Durak can end in one) scores nobody; otherwise every seat has its one event.
    expect($events)->toHaveCount($played['winners'] === [] ? 0 : count($played['bots']))
        ->and(DB::table('game_points')->whereNotNull('bot_profile_id')->where('game_id', '!=', $durak->id)->exists())->toBeFalse()
        ->and($events->where('delta', '>', 0)->count())->toBe(count($played['winners']));
});

it('plays nothing when a game has too few AI players of its own', function () {
    $chess = Game::query()->where('slug', 'chess')->firstOrFail();
    DB::table('bot_profiles')->where('home_game_id', $chess->id)->update(['is_enabled' => false]);

    expect(app(AiArena::class)->play($chess))->toBeNull()
        ->and(DB::table('ai_arena_games')->count())->toBe(0);
});
