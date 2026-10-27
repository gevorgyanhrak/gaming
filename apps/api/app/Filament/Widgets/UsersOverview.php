<?php

namespace App\Filament\Widgets;

use App\Enums\UserStatus;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Player numbers. Game statistics (games played, active rooms, bot vs. human games) join the
 * dashboard with gameplay in Phase 5. Counts are cached briefly; every query uses an index.
 */
class UsersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth('admin')->user()?->can('manage users') ?? false;
    }

    protected function getStats(): array
    {
        $stats = Cache::remember('admin:users-overview', 60, fn () => [
            'total' => User::query()->count(),
            'active_30d' => User::query()->where('last_active_at', '>=', now()->subDays(30))->count(),
            'recent' => User::query()->where('last_active_at', '>=', now()->subMinutes(15))->count(),
            'today' => User::query()->where('created_at', '>=', now()->startOfDay())->count(),
            'banned' => User::query()->where('status', UserStatus::Banned)->count(),
        ]);

        return [
            Stat::make('Registered users', number_format($stats['total'])),
            Stat::make('Active in the last 30 days', number_format($stats['active_30d'])),
            // Live presence (who is online right now) arrives with the real-time layer in Phase 5.
            Stat::make('Active in the last 15 minutes', number_format($stats['recent'])),
            Stat::make('Registered today', number_format($stats['today'])),
            Stat::make('Banned', number_format($stats['banned'])),
        ];
    }
}
