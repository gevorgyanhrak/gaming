<?php

use App\Gameplay\Lifecycle\RoomPresence;
use App\Gameplay\Matchmaking\Matchmaker;
use App\Models\VerificationCode;
use App\Platform\Achievements\Achievements;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schedule;

// Run by the "scheduler" service (php artisan schedule:work). Overlap locks expire after a minute
// (the default is a day): a run killed by a restart must not block matchmaking until tomorrow.
Schedule::command('app:lift-expired-bans')->everyMinute()->withoutOverlapping(1);
// The 5-second sweeps run inside the scheduler's process (the app:*-sweep commands do the same by
// hand): starting PHP for every run, 24 times a minute, cost far more than the sweeps themselves and
// slowed the game workers next to it.
Schedule::call(fn (Matchmaker $matchmaker) => $matchmaker->sweep())->name('matchmaking-sweep')->everyFiveSeconds()->withoutOverlapping(1);
Schedule::call(fn (RoomPresence $presence) => $presence->sweep())->name('abandonment-sweep')->everyFiveSeconds()->withoutOverlapping(1);
Schedule::command('model:prune', ['--model' => [VerificationCode::class]])->daily();

// Achievements: the day's and the week's first place, once the period is over.
Schedule::call(fn (Achievements $a) => $a->closePeriod('day', CarbonImmutable::now()->subDay()))->name('achievements-day')->dailyAt('00:05');
Schedule::call(fn (Achievements $a) => $a->closePeriod('week', CarbonImmutable::now()->subWeek()))->name('achievements-week')->weeklyOn(1, '00:10');
