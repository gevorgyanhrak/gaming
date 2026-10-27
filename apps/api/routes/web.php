<?php

use Illuminate\Support\Facades\Route;

// This application is an API. The SPA lives in apps/web.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
]));
