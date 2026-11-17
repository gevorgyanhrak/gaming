<?php

use App\Models\Favorite;
use App\Models\Game;
use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->chess = Game::factory()->create(['slug' => 'chess-fav']);
    $provider = SlotProvider::query()->create(['slug' => 'apexslots', 'name' => 'APEXSLOTS', 'is_enabled' => true]);
    $this->slot = SlotGame::query()->create([
        'slot_provider_id' => $provider->id, 'provider_game_id' => 'sevens', 'slug' => 'sevens',
        'name' => ['en' => 'Sevens'], 'min_bet' => 10, 'max_bet' => 1000, 'max_multiplier' => 100, 'is_enabled' => true,
    ]);
});

it('is only for signed-in players', function () {
    $this->getJson('/api/v1/me/favorites')->assertUnauthorized();
    $this->putJson('/api/v1/me/favorites/game/chess-fav')->assertUnauthorized();
});

it('adds games and slots once, newest first, and removes them', function () {
    $this->actingAs($this->user);

    $this->putJson('/api/v1/me/favorites/game/chess-fav')->assertOk()
        ->assertExactJson(['data' => [['kind' => 'game', 'slug' => 'chess-fav']]]);
    // A repeated tap changes nothing.
    $this->putJson('/api/v1/me/favorites/game/chess-fav')->assertOk();
    $this->putJson('/api/v1/me/favorites/slot/sevens')->assertOk()
        ->assertExactJson(['data' => [['kind' => 'slot', 'slug' => 'sevens'], ['kind' => 'game', 'slug' => 'chess-fav']]]);
    expect(Favorite::query()->count())->toBe(2);

    $this->deleteJson('/api/v1/me/favorites/game/chess-fav')->assertOk()
        ->assertExactJson(['data' => [['kind' => 'slot', 'slug' => 'sevens']]]);
    $this->deleteJson('/api/v1/me/favorites/game/chess-fav')->assertOk();
    $this->deleteJson('/api/v1/me/favorites/game/no-such-game')->assertOk();
});

it('refuses what the catalogue does not show, and hides favourites that leave it', function () {
    $this->actingAs($this->user);
    Game::factory()->hidden()->create(['slug' => 'secret']);

    $this->putJson('/api/v1/me/favorites/game/secret')->assertNotFound();
    $this->putJson('/api/v1/me/favorites/game/nope')->assertNotFound();
    $this->putJson('/api/v1/me/favorites/card/chess-fav')->assertNotFound();

    $this->putJson('/api/v1/me/favorites/slot/sevens')->assertOk();
    $this->slot->update(['is_enabled' => false]);
    $this->getJson('/api/v1/me/favorites')->assertExactJson(['data' => []]);
    // Still removable while it is off.
    $this->deleteJson('/api/v1/me/favorites/slot/sevens')->assertOk();
    expect(Favorite::query()->count())->toBe(0);
});

it('keeps each player\'s favourites to themselves', function () {
    $this->actingAs($this->user)->putJson('/api/v1/me/favorites/game/chess-fav')->assertOk();

    $this->actingAs(User::factory()->create())->getJson('/api/v1/me/favorites')->assertExactJson(['data' => []]);
});
