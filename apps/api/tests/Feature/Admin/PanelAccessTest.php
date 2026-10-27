<?php

use App\Models\User;

it('sends guests to the admin login', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets staff in according to their permissions', function (?string $role, array $allowed, array $denied) {
    signInToAdmin($role);

    foreach ($allowed as $path) {
        $this->get($path)->assertOk();
    }
    foreach ($denied as $path) {
        $this->get($path)->assertForbidden();
    }
})->with([
    'player' => [null, [], ['/admin', '/admin/users']],
    'moderator' => ['moderator', ['/admin', '/admin/users', '/admin/audit-logs'], ['/admin/games', '/admin/bot-profiles', '/admin/manage-settings', '/admin/content-pages']],
    'admin' => ['admin', ['/admin', '/admin/users', '/admin/games', '/admin/bot-profiles', '/admin/manage-settings', '/admin/content-pages', '/admin/audit-logs'], []],
]);

it('refuses banned staff and staff without an email address', function (array $attributes) {
    signInToAdmin('admin', $attributes);

    $this->get('/admin')->assertForbidden();
})->with([
    'banned' => [['status' => 'banned']],
    'no email' => [['email' => null, 'phone' => '+37477123456']],
]);

it('keeps the admin login separate from the player app', function () {
    signInToAdmin('admin');

    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('has no pages to create or delete games or to edit users and audit entries', function () {
    signInToAdmin('super_admin');

    $this->get('/admin/games/create')->assertNotFound();
    $this->get('/admin/users/create')->assertNotFound();
    $this->get('/admin/audit-logs/create')->assertNotFound();
    $this->get('/admin/users/'.User::factory()->create()->id.'/edit')->assertNotFound();
});
