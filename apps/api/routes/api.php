<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\UsernameAvailabilityController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\ContentPageController;
use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\MatchmakingController;
use App\Http\Controllers\Api\V1\Me\AvatarController;
use App\Http\Controllers\Api\V1\Me\ContactController;
use App\Http\Controllers\Api\V1\Me\HistoryController;
use App\Http\Controllers\Api\V1\Me\MeController;
use App\Http\Controllers\Api\V1\Me\PasswordController;
use App\Http\Controllers\Api\V1\Me\ProfileController;
use App\Http\Controllers\Api\V1\Me\RoomsController;
use App\Http\Controllers\Api\V1\Me\SettingsController;
use App\Http\Controllers\Api\V1\OnlineController;
use App\Http\Controllers\Api\V1\PublicProfileController;
use App\Http\Controllers\Api\V1\Rooms\ActionController;
use App\Http\Controllers\Api\V1\Rooms\RoomController;
use Illuminate\Support\Facades\Route;

// Unversioned infrastructure endpoints. Liveness is Laravel's built-in GET /up.
Route::get('health/ready', [HealthController::class, 'ready'])->name('health.ready');

Route::prefix('v1')->name('v1.')->group(function () {
    // Always available, even in maintenance mode: what the SPA needs to explain the situation
    // and to let staff sign in.
    Route::get('config', ConfigController::class)->name('config');
    Route::get('pages/{slug}', [ContentPageController::class, 'show'])->name('pages.show');
    Route::post('auth/login', [SessionController::class, 'store'])->middleware('throttle:auth')->name('auth.login');
    Route::post('auth/logout', [SessionController::class, 'destroy'])->middleware('auth:sanctum')->name('auth.logout');
    Route::get('me', [MeController::class, 'show'])
        ->middleware(['auth:sanctum', 'active', 'touch-last-active', 'throttle:account'])
        ->name('me.show');

    Route::middleware('maintenance')->group(function () {
        Route::get('games', [GameController::class, 'index'])->name('games.index');
        Route::get('games/{slug}', [GameController::class, 'show'])->name('games.show');

        Route::get('users/{username}', [PublicProfileController::class, 'show'])
            ->middleware('throttle:lookups')
            ->name('users.show');

        Route::prefix('auth')->name('auth.')->group(function () {
            Route::middleware('throttle:auth')->group(function () {
                Route::post('register', RegisterController::class)->name('register');
                Route::post('password/forgot', [PasswordResetController::class, 'forgot'])->name('password.forgot');
                Route::post('password/reset', [PasswordResetController::class, 'reset'])->name('password.reset');
            });

            Route::get('username-available', UsernameAvailabilityController::class)
                ->middleware('throttle:lookups')
                ->name('username-available');

            Route::middleware(['auth:sanctum', 'active', 'throttle:verification'])->group(function () {
                Route::post('verification/send', [VerificationController::class, 'send'])->name('verification.send');
                Route::post('verification/verify', [VerificationController::class, 'verify'])->name('verification.verify');
            });
        });

        Route::middleware(['auth:sanctum', 'active', 'touch-last-active', 'throttle:account'])
            ->prefix('me')
            ->name('me.')
            ->group(function () {
                Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
                Route::post('avatar', [AvatarController::class, 'store'])->middleware('throttle:uploads')->name('avatar.store');
                Route::delete('avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');
                Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
                Route::put('password', [PasswordController::class, 'update'])->name('password.update');
                Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:verification')->name('contact.store');
                Route::post('contact/verify', [ContactController::class, 'verify'])->middleware('throttle:verification')->name('contact.verify');
                Route::get('rooms', [RoomsController::class, 'index'])->name('rooms.index');
                Route::get('games', [HistoryController::class, 'games'])->name('games');
                Route::get('stats', [HistoryController::class, 'stats'])->name('stats');
            });

        Route::get('stats/online', OnlineController::class)->middleware('throttle:lookups')->name('stats.online');

        Route::middleware(['auth:sanctum', 'active', 'touch-last-active', 'throttle:game'])
            ->prefix('matchmaking')
            ->name('matchmaking.')
            ->group(function () {
                Route::get('/', [MatchmakingController::class, 'show'])->name('show');
                Route::post('heartbeat', [MatchmakingController::class, 'heartbeat'])->name('heartbeat');
                Route::delete('/', [MatchmakingController::class, 'destroy'])->name('destroy');
                Route::middleware('verified-contact')->group(function () {
                    Route::post('/', [MatchmakingController::class, 'store'])->name('store');
                    Route::post('accept-bot', [MatchmakingController::class, 'acceptBot'])->name('accept-bot');
                });
            });

        Route::middleware(['auth:sanctum', 'active', 'touch-last-active', 'throttle:game'])
            ->prefix('rooms')
            ->name('rooms.')
            ->group(function () {
                Route::middleware('verified-contact')->group(function () {
                    Route::post('/', [RoomController::class, 'store'])->name('store');
                    Route::post('join', [RoomController::class, 'join'])->name('join');
                });
                Route::middleware('can:view,room')->group(function () {
                    Route::get('{room}', [RoomController::class, 'show'])->name('show');
                    Route::get('{room}/state', [RoomController::class, 'state'])->name('state');
                    Route::post('{room}/actions', [ActionController::class, 'store'])->middleware('throttle:game-actions')->name('actions.store');
                    Route::post('{room}/leave', [RoomController::class, 'leave'])->name('leave');
                });
            });
    });
});
