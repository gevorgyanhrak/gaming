<?php

use App\Models\VerificationCode;
use Illuminate\Support\Facades\Schedule;

// Run by the "scheduler" service (php artisan schedule:work). Overlap locks expire after a minute
// (the default is a day): a run killed by a restart must not block matchmaking until tomorrow.
Schedule::command('app:lift-expired-bans')->everyMinute()->withoutOverlapping(1);
Schedule::command('app:matchmaking-sweep')->everyFiveSeconds()->withoutOverlapping(1);
Schedule::command('model:prune', ['--model' => [VerificationCode::class]])->daily();
