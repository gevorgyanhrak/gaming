<?php

use App\Filament\Resources\BotProfiles\Pages\CreateBotProfile;
use App\Filament\Resources\Games\Pages\EditGame;
use App\Models\AuditLog;
use App\Models\BotProfile;
use App\Models\Game;
use App\Models\GameMode;
use Database\Seeders\BotSeeder;
use Database\Seeders\ContentPageSeeder;
use Database\Seeders\GameModeSeeder;
use Database\Seeders\GameSeeder;
use Livewire\Livewire;

it('edits a game in every language and audits the change', function () {
    $admin = signInToAdmin('admin');
    $game = Game::factory()->create(['slug' => 'chess', 'name' => ['en' => 'Chess']]);

    Livewire::test(EditGame::class, ['record' => $game->getRouteKey()])
        ->assertSchemaStateSet(['name.en' => 'Chess'])
        ->fillForm(['name' => ['en' => 'Chess', 'ru' => 'Шахматы', 'hy' => ''], 'bot_fallback_mode' => 'auto', 'bot_fallback_timeout_seconds' => 20])
        ->call('save')
        ->assertHasNoFormErrors();

    $game->refresh();
    expect($game->getTranslations('name'))->toBe(['en' => 'Chess', 'ru' => 'Шахматы'])
        ->and($game->bot_fallback_mode->value)->toBe('auto')
        ->and($game->bot_fallback_timeout_seconds)->toBe(20);

    $log = AuditLog::query()->where('action', 'game.updated')->sole();
    expect($log->actor_id)->toBe($admin->id)->and($log->changes)->toHaveKey('bot_fallback_mode');
});

it('lets admins switch a game to playable only once its engine exists', function () {
    signInToAdmin('admin');
    $game = Game::factory()->create(['is_enabled' => false]);

    Livewire::test(EditGame::class, ['record' => $game->getRouteKey()])
        ->fillForm(['is_enabled' => true])
        ->call('save');

    expect($game->refresh()->is_enabled)->toBeFalse();

    $mode = ticTacToe();
    $registered = $mode->game()->firstOrFail();
    $registered->forceFill(['is_enabled' => false])->save();

    Livewire::test(EditGame::class, ['record' => $registered->getRouteKey()])
        ->fillForm(['is_enabled' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($registered->refresh()->is_enabled)->toBeTrue();
});

it('creates a bot with game assignments', function () {
    signInToAdmin('admin');
    $game = Game::factory()->create();

    Livewire::test(CreateBotProfile::class)
        ->fillForm([
            'name' => 'Ani',
            'is_enabled' => true,
            'gameProfiles' => [['game_id' => $game->id, 'difficulty' => 'hard', 'is_enabled' => true]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $bot = BotProfile::query()->where('name', 'Ani')->sole();
    expect($bot->gameProfiles()->sole()->difficulty->value)->toBe('hard');
});

it('requires unique bot names', function () {
    signInToAdmin('admin');
    BotProfile::factory()->create(['name' => 'Aram']);

    Livewire::test(CreateBotProfile::class)
        ->fillForm(['name' => 'Aram'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

it('lists enabled modes on the public game page', function () {
    $game = Game::factory()->create(['slug' => 'chess']);
    GameMode::factory()->for($game)->create(['slug' => 'blitz', 'name' => ['en' => 'Blitz'], 'sort_order' => 1]);
    GameMode::factory()->for($game)->create(['slug' => 'hidden', 'is_enabled' => false]);

    $this->getJson('/api/v1/games/chess')
        ->assertOk()
        ->assertJsonCount(1, 'data.modes')
        ->assertJsonPath('data.modes.0.slug', 'blitz')
        ->assertJsonPath('data.modes.0.name', 'Blitz');
});

it('seeds modes, bots and draft pages without overwriting admin changes', function () {
    $this->seed([GameSeeder::class, GameModeSeeder::class, BotSeeder::class, ContentPageSeeder::class]);

    expect(GameMode::query()->count())->toBe(6)
        ->and(BotProfile::query()->pluck('name')->sort()->values()->all())->toBe(['Alex', 'Aram', 'Daniel', 'David', 'Levon', 'Michael', 'Robert'])
        ->and(BotProfile::query()->where('name', 'Levon')->sole()->gameProfiles()->count())->toBe(5);

    BotProfile::query()->where('name', 'Alex')->update(['is_enabled' => false]);
    $this->seed([GameModeSeeder::class, BotSeeder::class, ContentPageSeeder::class]);

    expect(GameMode::query()->count())->toBe(6)
        ->and(BotProfile::query()->count())->toBe(7)
        ->and(BotProfile::query()->where('name', 'Alex')->sole()->is_enabled)->toBeFalse();
});
