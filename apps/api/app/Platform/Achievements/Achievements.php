<?php

namespace App\Platform\Achievements;

use App\Models\Game;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Awards achievements from the server's own records (AchievementCatalog) and tells the person on their channel
 * (achievement.unlocked) so a congratulation shows. Each is earned once (a title once per day or week).
 */
class Achievements
{
    /** After a person's game is over (and its win, if any, recorded): big win, streak, experience. */
    public function afterGame(int $userId, int $gameId, string $roomId): void
    {
        $slug = (string) Game::query()->whereKey($gameId)->value('slug');
        if (in_array($slug, AchievementCatalog::BIG_WIN_GAMES, true)) {
            $won = (int) DB::table('game_wins')->where('room_public_id', $roomId)->where('user_id', $userId)->value('amount');
            if ($won >= AchievementCatalog::BIG_WIN) {
                $this->award($userId, 'big_win', $gameId, '', $won);
            }
        }
        $streak = (int) DB::table('user_game_stats')->where('user_id', $userId)->where('game_id', $gameId)->value('current_streak');
        if ($streak >= AchievementCatalog::STREAK) {
            $this->award($userId, 'streak', $gameId, '', $streak);
        }
        $played = (int) DB::table('user_game_stats')->where('user_id', $userId)->sum('played');
        foreach (AchievementCatalog::VETERAN as $n) {
            if ($played >= $n) {
                $this->award($userId, "veteran_{$n}", null, '', $played);
            }
        }
    }

    /** The day's (or week's) first place, once it is over: "Daily best player" / "Weekly champion". */
    public function closePeriod(string $period, CarbonImmutable $at): void
    {
        $start = $period === 'day' ? $at->startOfDay() : $at->startOfWeek();
        $end = $period === 'day' ? $start->addDay() : $start->addWeek();
        $first = DB::table('game_wins')->whereBetween('won_at', [$start, $end->subSecond()])
            ->groupBy('user_id')->orderByRaw('SUM(amount) DESC')
            ->first(['user_id', DB::raw('SUM(amount) AS total')]);
        if ($first !== null) {
            $this->award((int) $first->user_id, $period === 'day' ? 'daily_best' : 'weekly_champion', null, $start->toDateString(), (int) $first->total);
        }
    }

    /**
     * A person's achievements, newest first.
     *
     * @return list<array{key: string, game: array{slug: string, name: string}|null, period: string|null, value: int|null, earned_at: string}>
     */
    public function of(int $userId): array
    {
        $games = Game::query()->get(['id', 'slug', 'name'])->keyBy('id');

        return DB::table('user_achievements')->where('user_id', $userId)->orderByDesc('earned_at')->get()
            ->map(function ($a) use ($games) {
                $game = $a->game_id === null ? null : $games->get($a->game_id);

                return [
                    'key' => (string) $a->key,
                    'game' => $game === null ? null : ['slug' => $game->slug, 'name' => $game->name],
                    'period' => $a->period === '' ? null : (string) $a->period,
                    'value' => $a->value === null ? null : (int) $a->value,
                    'earned_at' => CarbonImmutable::parse($a->earned_at)->toIso8601String(),
                ];
            })->values()->all();
    }

    private function award(int $userId, string $key, ?int $gameId, string $period, int $value): void
    {
        $inserted = DB::table('user_achievements')->insertOrIgnore([
            'user_id' => $userId, 'key' => $key, 'game_id' => $gameId, 'scope' => (string) ($gameId ?? ''), 'period' => $period,
            'value' => $value, 'earned_at' => now(),
        ]);
        if ($inserted > 0) {
            $game = $gameId === null ? null : Game::query()->find($gameId, ['slug', 'name']);
            AchievementUnlocked::dispatch($userId, [
                'key' => $key,
                'game' => $game === null ? null : ['slug' => $game->slug, 'name' => $game->name],
                'period' => $period === '' ? null : $period,
                'value' => $value,
            ]);
        }
    }
}
