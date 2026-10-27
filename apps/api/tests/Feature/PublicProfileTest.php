<?php

use App\Models\User;

it('shows a public profile without contact details', function () {
    $user = User::factory()->create(['username' => 'alice', 'email' => 'alice@example.com']);
    $user->profile->fill(['display_name' => 'Alice', 'country' => 'AM'])->save();

    $this->getJson('/api/v1/users/alice')
        ->assertOk()
        ->assertJsonPath('data.username', 'alice')
        ->assertJsonPath('data.display_name', 'Alice')
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.phone')
        ->assertJsonMissingPath('data.id');
});

it('hides banned and unknown users', function (string $username) {
    User::factory()->banned()->create(['username' => 'banned_one']);

    $this->getJson("/api/v1/users/{$username}")->assertNotFound();
})->with(['banned_one', 'nobody']);
