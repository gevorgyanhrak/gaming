<?php

use App\Models\Game;
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

it('seeds the five launch games without overwriting admin changes', function () {
    $this->seed(GameSeeder::class);

    expect(Game::query()->ordered()->pluck('slug')->all())
        ->toBe(['nardi', 'chess', 'checkers', 'durak', 'poker'])
        ->and(Game::query()->where('is_enabled', true)->exists())->toBeFalse();

    Game::query()->where('slug', 'chess')->firstOrFail()
        ->setTranslation('description', 'en', 'Edited by an admin')
        ->save();

    $this->seed(GameSeeder::class);

    expect(Game::query()->count())->toBe(5)
        ->and(Game::query()->where('slug', 'chess')->firstOrFail()->description)->toBe('Edited by an admin');
});
