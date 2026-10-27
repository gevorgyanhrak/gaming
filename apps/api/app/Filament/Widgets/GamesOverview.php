<?php

namespace App\Filament\Widgets;

use App\Enums\RoomStatus;
use App\Models\GameRoom;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

/**
 * Game activity. Counts are cached briefly; every query uses an index on game_rooms.
 */
class GamesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return auth('admin')->user()?->can('access admin') ?? false;
    }

    protected function getStats(): array
    {
        $stats = Cache::remember('admin:games-overview', 60, fn () => [
            'total' => GameRoom::query()->where('status', RoomStatus::Finished)->count(),
            'today' => GameRoom::query()->where('status', RoomStatus::Finished)->where('finished_at', '>=', now()->startOfDay())->count(),
            'active' => GameRoom::query()->where('status', RoomStatus::Active)->count(),
            'bot' => GameRoom::query()->where('status', RoomStatus::Finished)->where('has_bot', true)->count(),
        ]);

        return [
            Stat::make('Games played', number_format($stats['total'])),
            Stat::make('Games played today', number_format($stats['today'])),
            Stat::make('Games in progress', number_format($stats['active'])),
            Stat::make('Against bots / players', number_format($stats['bot']).' / '.number_format($stats['total'] - $stats['bot'])),
        ];
    }
}
