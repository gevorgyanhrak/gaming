<?php

namespace App\Platform\Wins;

use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Who won the most Fishkas — today, this week, this month — overall or in one game, from game_wins (the ledger's
 * results of finished games; people only, AI never wins Fishkas). Each row: rank, player, total won, how many
 * games won and, overall, the game they won most in. Kept 30 s: boards only have to be about live.
 */
class Leaderboards
{
    public const PERIODS = ['day', 'week', 'month'];

    /**
     * @return list<array{rank: int, total: int, wins: int, player: array{username: string, name: string, avatar_url: string|null, level: int}, game: array{slug: string, name: string}|null}>
     */
    public function top(string $period, ?Game $game = null, int $limit = 10): array
    {
        $key = "leaderboard:{$period}:".($game->id ?? 'all').':'.app()->getLocale();

        return Cache::remember($key, 30, fn () => $this->compute($period, $game, $limit));
    }

    /**
     * @return list<array{rank: int, total: int, wins: int, player: array{username: string, name: string, avatar_url: string|null, level: int}, game: array{slug: string, name: string}|null}>
     */
    private function compute(string $period, ?Game $game, int $limit): array
    {
        $since = match ($period) {
            'day' => CarbonImmutable::now()->startOfDay(),
            'week' => CarbonImmutable::now()->startOfWeek(),
            default => CarbonImmutable::now()->startOfMonth(),
        };
        $rows = DB::table('game_wins')
            ->join('users', 'users.id', '=', 'game_wins.user_id')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->where('game_wins.won_at', '>=', $since)
            ->when($game !== null, fn ($q) => $q->where('game_wins.game_id', $game->id))
            ->groupBy('game_wins.user_id', 'users.username', 'users.level', 'user_profiles.display_name', 'user_profiles.avatar_path')
            ->orderByRaw('SUM(game_wins.amount) DESC')
            ->limit($limit)
            ->get(['game_wins.user_id', 'users.username', 'users.level', 'user_profiles.display_name', 'user_profiles.avatar_path', DB::raw('SUM(game_wins.amount) AS total'), DB::raw('COUNT(*) AS wins')]);

        // Overall boards: each player's best game in the period.
        $best = [];
        if ($game === null && $rows->isNotEmpty()) {
            $byGame = DB::table('game_wins')->where('won_at', '>=', $since)->whereIn('user_id', $rows->pluck('user_id'))
                ->groupBy('user_id', 'game_id')->get(['user_id', 'game_id', DB::raw('SUM(amount) AS total')]);
            foreach ($byGame as $r) {
                if (! isset($best[$r->user_id]) || $r->total > $best[$r->user_id]->total) {
                    $best[$r->user_id] = $r;
                }
            }
        }
        $games = Game::query()->get(['id', 'slug', 'name'])->keyBy('id');

        $board = [];
        foreach ($rows->values() as $i => $row) {
            $g = $game ?? (isset($best[$row->user_id]) ? $games->get($best[$row->user_id]->game_id) : null);
            $board[] = [
                'rank' => $i + 1,
                'total' => (int) $row->total,
                'wins' => (int) $row->wins,
                'player' => [
                    'username' => (string) $row->username,
                    'name' => (string) ($row->display_name ?? $row->username),
                    'avatar_url' => $row->avatar_path === null ? null : Storage::disk('public')->url((string) $row->avatar_path),
                    'level' => (int) $row->level,
                ],
                'game' => $g === null ? null : ['slug' => $g->slug, 'name' => $g->name],
            ];
        }

        return $board;
    }
}
