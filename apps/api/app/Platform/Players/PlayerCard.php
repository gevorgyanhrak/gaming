<?php

namespace App\Platform\Players;

use App\Enums\RoomStatus;
use App\Models\Game;
use App\Models\User;
use App\Platform\Achievements\Achievements;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Levels\Levels;
use Illuminate\Support\Facades\DB;

/**
 * What the player popup shows about a person (tapping an avatar at a table): level and progress, Fishkas, games,
 * wins, losses and win rate, the biggest win, the favourite game, the current streak, achievements, whether they
 * are online and what they are playing now. All from the server's own records. Never anything private (contacts).
 */
class PlayerCard
{
    /** Seen within this many minutes counts as online. */
    public const ONLINE_MINUTES = 5;

    public function __construct(
        private readonly Levels $levels,
        private readonly FishkaWallets $wallets,
        private readonly Achievements $achievements,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $games = Game::query()->get(['id', 'slug', 'name'])->keyBy('id');
        $game = fn (?int $id) => $id === null || ! $games->has($id) ? null : ['slug' => $games[$id]->slug, 'name' => $games[$id]->name];

        $stats = DB::table('user_game_stats')->where('user_id', $user->id)->get();
        $played = (int) $stats->sum('played');
        $wins = (int) $stats->sum('wins');
        $favourite = $stats->sortByDesc('played')->first();
        $biggest = DB::table('game_wins')->where('user_id', $user->id)->orderByDesc('amount')->first(['amount', 'game_id']);
        $playing = DB::table('game_participants')
            ->join('game_rooms', 'game_rooms.id', '=', 'game_participants.game_room_id')
            ->where('game_participants.user_id', $user->id)->where('game_participants.is_active', true)
            ->where('game_rooms.status', RoomStatus::Active->value)
            ->value('game_rooms.game_id');
        $progress = $this->levels->progress($user);

        return [
            'username' => $user->username,
            'name' => $user->profile->display_name ?? $user->username,
            'avatar_url' => $user->profile->avatarUrl(),
            'level' => $user->level,
            'level_xp' => $progress['level_xp'],
            'xp' => $progress['xp'],
            'next_level_xp' => $progress['next_level_xp'],
            'balance' => (int) $this->wallets->wallet($user)->balance,
            'played' => $played,
            'wins' => $wins,
            'losses' => (int) $stats->sum('losses'),
            'win_rate' => $played > 0 ? (int) round(100 * $wins / $played) : null,
            'biggest_win' => $biggest === null ? null : ['amount' => (int) $biggest->amount, 'game' => $game((int) $biggest->game_id)],
            'favourite_game' => $favourite === null || (int) $favourite->played === 0 ? null : $game((int) $favourite->game_id),
            'streak' => (int) $stats->max('current_streak'),
            'online' => $user->last_active_at !== null && $user->last_active_at->gt(now()->subMinutes(self::ONLINE_MINUTES)),
            'playing' => $playing === null ? null : $game((int) $playing),
            'achievements' => array_slice($this->achievements->of($user->id), 0, 6),
        ];
    }
}
