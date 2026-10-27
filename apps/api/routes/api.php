<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\GameController;
use Illuminate\Support\Facades\Route;

// Unversioned infrastructure endpoints. Liveness is Laravel's built-in GET /up.
Route::get('health/ready', [HealthController::class, 'ready'])->name('health.ready');

Route::prefix('v1')->name('v1.')->group(function () {
    Route::get('config', ConfigController::class)->name('config');

    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::get('games/{slug}', [GameController::class, 'show'])->name('games.show');
});
