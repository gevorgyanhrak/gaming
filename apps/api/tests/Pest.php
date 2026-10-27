<?php

use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the application and run against the MySQL "testing"
| database (see phpunit.xml); TestCase refreshes it and refuses any other
| database. Unit tests stay framework-free; game engines are tested there.
|
*/

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * The most recent verification code sent to an email address or phone number.
 * Requires Notification::fake().
 */
function codeSentTo(string $destination): string
{
    $code = null;

    Notification::assertSentOnDemand(
        VerificationCodeNotification::class,
        function (VerificationCodeNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($destination, &$code) {
            if (! in_array($destination, $notifiable->routes, true)) {
                return false;
            }

            $code = $notification->code;

            return true;
        },
    );

    return (string) $code;
}

/** A 6-digit code guaranteed to differ from the given one. */
function wrongCode(string $code): string
{
    return str_pad((string) (((int) $code + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);
}

/**
 * A staff account with the given role (roles are seeded on demand), signed in to the admin panel.
 * Pass null for a regular player.
 */
function signInToAdmin(?string $role = 'super_admin', array $attributes = []): User
{
    test()->seed(RoleSeeder::class);

    $user = User::factory()->create($attributes);
    if ($role !== null) {
        $user->assignRole($role);
    }

    test()->actingAs($user, 'admin');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    return $user;
}
