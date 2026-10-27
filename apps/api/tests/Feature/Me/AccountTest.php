<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('returns the account without secrets', function () {
    $this->actingAs(User::factory()->create(['username' => 'alice']));

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.username', 'alice')
        ->assertJsonPath('data.can_access_admin', false)
        ->assertJsonPath('data.has_password', true)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token')
        ->assertJsonStructure(['data' => ['profile' => ['display_name', 'avatar_url', 'bio', 'country', 'preferences' => ['sound']]]]);
});

it('updates the profile', function () {
    $this->actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me/profile', ['display_name' => '  Alice  ', 'bio' => 'Nardi fan', 'country' => 'AM'])
        ->assertOk()
        ->assertJsonPath('data.profile.display_name', 'Alice')
        ->assertJsonPath('data.profile.country', 'AM');

    $this->patchJson('/api/v1/me/profile', ['display_name' => ''])
        ->assertOk()
        ->assertJsonPath('data.profile.display_name', null)
        ->assertJsonPath('data.profile.bio', 'Nardi fan');
});

it('validates the profile', function (array $input, string $field) {
    $this->actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me/profile', $input)->assertJsonValidationErrors($field);
})->with([
    [['country' => 'XX'], 'country'],
    [['display_name' => str_repeat('a', 41)], 'display_name'],
    [['bio' => str_repeat('a', 501)], 'bio'],
]);

it('stores avatars as square WebP images and replaces the previous one', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    $this->post('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg', 400, 300)], ['Accept' => 'application/json'])
        ->assertOk();
    $first = auth()->user()->profile()->first()->avatar_path;

    expect($first)->toEndWith('.webp');
    Storage::disk('public')->assertExists($first);
    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($first));
    expect([$width, $height])->toBe([256, 256]);

    $this->post('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('new.png', 128, 128)], ['Accept' => 'application/json'])
        ->assertOk();

    Storage::disk('public')->assertMissing($first);

    $this->deleteJson('/api/v1/me/avatar')->assertOk()->assertJsonPath('data.profile.avatar_url', null);
});

it('rejects invalid avatars', function (UploadedFile $file) {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    $this->post('/api/v1/me/avatar', ['avatar' => $file], ['Accept' => 'application/json'])
        ->assertJsonValidationErrors('avatar');
})->with([
    'too small' => fn () => UploadedFile::fake()->image('tiny.png', 32, 32),
    'not an image' => fn () => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
    'too large' => fn () => UploadedFile::fake()->image('big.jpg', 500, 500)->size(3000),
]);

it('updates settings', function () {
    $this->actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me/settings', ['locale' => 'ru', 'preferences' => ['sound' => false]])
        ->assertOk()
        ->assertJsonPath('data.locale', 'ru')
        ->assertJsonPath('data.profile.preferences.sound', false);

    $this->patchJson('/api/v1/me/settings', ['locale' => 'de'])->assertJsonValidationErrors('locale');
    $this->patchJson('/api/v1/me/settings', ['preferences' => ['admin' => true]])->assertJsonValidationErrors('preferences');
});

it('changes the password after checking the current one', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->putJson('/api/v1/me/password', [
        'current_password' => 'wrong-one-1',
        'password' => 'brand-new-pass-1',
        'password_confirmation' => 'brand-new-pass-1',
    ])->assertJsonValidationErrors('current_password');

    $this->putJson('/api/v1/me/password', [
        'current_password' => 'password1',
        'password' => 'brand-new-pass-1',
        'password_confirmation' => 'brand-new-pass-1',
    ])->assertNoContent();

    expect(Hash::check('brand-new-pass-1', $user->fresh()->password))->toBeTrue();
});

it('adds a phone number after it is confirmed with a code', function () {
    Notification::fake();
    $user = User::factory()->create(['phone' => null]);
    $this->actingAs($user);

    $this->postJson('/api/v1/me/contact', ['channel' => 'phone', 'phone' => '+374 77 123 456'])->assertAccepted();
    expect($user->fresh()->phone)->toBeNull();

    $this->postJson('/api/v1/me/contact/verify', ['channel' => 'phone', 'code' => codeSentTo('+37477123456')])
        ->assertOk()
        ->assertJsonPath('data.phone', '+37477123456')
        ->assertJsonPath('data.phone_verified', true);
});

it('refuses contacts that are taken or unchanged', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $this->actingAs(User::factory()->create(['email' => 'mine@example.com']));

    $this->postJson('/api/v1/me/contact', ['channel' => 'email', 'email' => 'TAKEN@example.com'])->assertJsonValidationErrors('email');
    $this->postJson('/api/v1/me/contact', ['channel' => 'email', 'email' => 'mine@example.com'])->assertJsonValidationErrors('email');
});

it('records last activity at most once per interval', function () {
    $user = User::factory()->create(['last_active_at' => null]);
    $this->actingAs($user);

    $this->getJson('/api/v1/me')->assertOk();
    $first = $user->fresh()->last_active_at;
    expect($first)->not->toBeNull();

    $this->travel(2)->minutes();
    $this->getJson('/api/v1/me');
    expect($user->fresh()->last_active_at->equalTo($first))->toBeTrue();

    $this->travel(4)->minutes();
    $this->getJson('/api/v1/me');
    expect($user->fresh()->last_active_at->gt($first))->toBeTrue();
});

it('signs out a user who was banned during their session', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->forceFill(['status' => 'banned'])->save();

    $this->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('status', 'banned');
});
