<?php

use App\Models\BotProfile;
use App\Models\Game;
use App\Models\GameCategory;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
use Database\Seeders\GameSeeder;

beforeEach(function () {
    config(['platform.supported_locales' => ['en', 'ru', 'hy'], 'app.fallback_locale' => 'en']);
});

it('lists visible games in catalog order', function () {
    Game::factory()->create(['slug' => 'second', 'sort_order' => 20]);
    Game::factory()->create(['slug' => 'first', 'sort_order' => 10]);
    Game::factory()->hidden()->create(['slug' => 'secret']);

    $this->getJson('/api/v1/games')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'first')
        ->assertJsonPath('data.1.slug', 'second');
});

it('gives each game its category, translated, or none', function () {
    $category = GameCategory::query()->where('slug', 'card')->firstOrFail();
    Game::factory()->create(['slug' => 'first', 'sort_order' => 10, 'game_category_id' => $category->id]);
    Game::factory()->create(['slug' => 'second', 'sort_order' => 20]);

    $this->getJson('/api/v1/games', ['Accept-Language' => 'ru'])
        ->assertOk()
        ->assertJsonPath('data.0.category', ['slug' => 'card', 'name' => 'Карточные игры', 'sort_order' => 20])
        ->assertJsonPath('data.1.category', null);
});

it('keeps a deleted category\'s games in the catalog', function () {
    $category = GameCategory::query()->where('slug', 'board')->firstOrFail();
    $game = Game::factory()->create(['game_category_id' => $category->id]);

    $category->delete();

    expect($game->fresh()->game_category_id)->toBeNull();
});

it('seeds the launch games into their categories', function () {
    $this->seed(GameSeeder::class);

    expect(Game::query()->with('category')->get()->mapWithKeys(fn (Game $g) => [$g->slug => $g->category?->slug])->all())->toEqual([
        'nardi' => 'board', 'chess' => 'board', 'checkers' => 'board', 'durak' => 'card', 'poker' => 'card', 'blackjack' => 'card', 'blot' => 'card', 'domino' => 'board', 'ludo' => 'board', 'pool' => 'board', 'rummy' => 'card',
    ]);
});

it('returns catalog fields without rules', function () {
    Game::factory()->playable()->create(['slug' => 'chess', 'min_players' => 2, 'max_players' => 2]);

    $game = $this->getJson('/api/v1/games')->assertOk()->json('data.0');

    expect($game)->toHaveKeys(['slug', 'name', 'description', 'icon_url', 'thumbnail_url', 'min_players', 'max_players', 'is_playable'])
        ->not->toHaveKey('rules')
        ->and($game['is_playable'])->toBeTrue();
});

it('translates names from Accept-Language and falls back to English', function () {
    Game::factory()->create([
        'slug' => 'chess',
        'name' => ['en' => 'Chess', 'ru' => 'Шахматы'],
        'description' => ['en' => 'Strategy'],
    ]);

    $this->getJson('/api/v1/games', ['Accept-Language' => 'ru-RU,ru;q=0.9'])
        ->assertHeader('Content-Language', 'ru')
        ->assertJsonPath('data.0.name', 'Шахматы')
        ->assertJsonPath('data.0.description', 'Strategy');

    $this->getJson('/api/v1/games', ['Accept-Language' => 'de'])
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('data.0.name', 'Chess');
});

it('shows a visible game with its rules', function () {
    Game::factory()->create(['slug' => 'nardi', 'rules' => ['en' => "First.\n\nSecond."]]);

    $this->getJson('/api/v1/games/nardi')
        ->assertOk()
        ->assertJsonPath('data.slug', 'nardi')
        ->assertJsonPath('data.rules', "First.\n\nSecond.");
});

it('returns a generic 404 for hidden or unknown games', function (string $slug) {
    Game::factory()->hidden()->create(['slug' => 'secret']);

    $this->getJson("/api/v1/games/{$slug}")
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not found.']);
})->with(['secret', 'does-not-exist']);

it('seeds the games without overwriting admin changes', function () {
    $this->seed(GameSeeder::class);

    expect(Game::query()->ordered()->pluck('slug')->all())
        ->toBe(['nardi', 'chess', 'checkers', 'durak', 'poker', 'blackjack', 'blot', 'domino', 'ludo', 'pool', 'rummy'])
        ->and(Game::query()->where('is_enabled', true)->pluck('slug')->all())->toBe(['nardi', 'chess', 'checkers', 'durak', 'poker', 'blackjack', 'blot', 'domino', 'ludo', 'pool', 'rummy']);

    Game::query()->where('slug', 'chess')->firstOrFail()
        ->setTranslation('description', 'en', 'Edited by an admin')
        ->save();

    $this->seed(GameSeeder::class);

    expect(Game::query()->count())->toBe(11)
        ->and(Game::query()->where('slug', 'chess')->firstOrFail()->description)->toBe('Edited by an admin');
});

it('says how many people play a game now — people only, never AI — where its setting shows it', function () {
    $chess = Game::factory()->create(['slug' => 'chess', 'sort_order' => 10]);
    $poker = Game::factory()->create(['slug' => 'poker', 'sort_order' => 20, 'show_online' => false]);
    $table = function (Game $game, string $status, array $seats) {
        $mode = GameMode::factory()->for($game)->create();
        $room = GameRoom::query()->create(['game_id' => $game->id, 'game_mode_id' => $mode->id, 'type' => 'matchmaking', 'status' => $status, 'seats' => count($seats)]);
        foreach ($seats as $seat => $who) {
            $room->participants()->create(['seat' => $seat, 'is_active' => true, ...$who]);
        }
    };
    $table($chess, 'active', [['user_id' => User::factory()->create()->id], ['bot_profile_id' => BotProfile::factory()->create()->id]]);
    $table($chess, 'active', [['user_id' => User::factory()->create()->id], ['user_id' => User::factory()->create()->id, 'ai_controlled' => true]]);
    $table($chess, 'finished', [['user_id' => User::factory()->create()->id], ['user_id' => User::factory()->create()->id]]);
    $table($poker, 'active', [['user_id' => User::factory()->create()->id], ['user_id' => User::factory()->create()->id]]);

    $this->getJson('/api/v1/games')
        ->assertOk()
        // Two people at chess tables now: the bot, the AI-held seat and the finished table don't count.
        ->assertJsonPath('data.0.show_online', true)->assertJsonPath('data.0.playing_now', 2)
        ->assertJsonPath('data.1.show_online', false)->assertJsonPath('data.1.playing_now', null);
});
