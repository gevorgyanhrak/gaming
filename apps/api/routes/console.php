<?php

use App\Models\VerificationCode;
use Illuminate\Support\Facades\Schedule;

// Run by the "scheduler" service (php artisan schedule:work).
Schedule::command('app:lift-expired-bans')->everyMinute()->withoutOverlapping();
Schedule::command('app:matchmaking-sweep')->everyFiveSeconds()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [VerificationCode::class]])->daily();
