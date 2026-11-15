<?php

namespace App\Platform\Rankings;

use App\Enums\ParticipantResult;
use App\Enums\UserStatus;
use App\Models\Game;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Platform\Levels\Levels;
use App\Platform\Settings\Settings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Rankings (owner, 2026-11-16), all from real data:
 * - per-game points for the games in `rankings.points_games`: a win adds `rankings.win_points`, a loss (also leaving
 *   or timing out) takes `rankings.loss_points`, a draw changes nothing; never below 0. People and AI players alike
 *   (an AI by its bot profile), each game on its own. One event per finished game and seat (unique), so a game
 *   never counts twice;
 * - the games in `rankings.chips_games` (Poker, Blackjack) rank people by their Fishkas;
 * - across the platform: the highest levels, and the most Fishkas (people only: AI has no wallet, and its level is
 *   set by the admin, not earned).
 * Boards are kept 30 s and dropped when a game changes them.
 */
class Rankings
{
    public const TOP = 10;

    private const TTL = 30;

    public function __construct(
        private readonly Settings $settings,
        private readonly Levels $levels,
    ) {}

    /** How the game's page ranks players: 'points', 'chips', or null (no board). */
    public function kindFor(Game $game): ?string
    {
        return match (true) {
            in_array($game->slug, (array) $this->settings->get('rankings.points_games'), true) => 'points',
            in_array($game->slug, (array) $this->settings->get('rankings.chips_games'), true) => 'chips',
            default => null,
        };
    }

    /** Points for a seat's result in a finished game (inside the game's transaction). */
    public function record(GameRoom $room, GameParticipant $participant, ParticipantResult $result): void
    {
        $game = $room->game;
        if ($this->kindFor($game) !== 'points' || $result === ParticipantResult::Draw) {
            return;
        }
        $owner = $participant->user_id !== null
            ? ['user_id' => $participant->user_id]
            : ($participant->bot_profile_id !== null ? ['bot_profile_id' => $participant->bot_profile_id] : null);
        if ($owner === null) {
            return;
        }

        DB::table('game_points')->insertOrIgnore([...$owner, 'game_id' => $game->id, 'created_at' => now(), 'updated_at' => now()]);
        $row = DB::table('game_points')->where('game_id', $game->id)->where($owner)->lockForUpdate()->first();
        if ($row === null) {
            return;
        }

        $won = $result === ParticipantResult::Win;
        $delta = $won
            ? (int) $this->settings->get('rankings.win_points')
            : -min((int) $row->points, (int) $this->settings->get('rankings.loss_points'));
        $inserted = DB::table('game_point_events')->insertOrIgnore([
            'game_points_id' => $row->id,
            'room_public_id' => $room->public_id,
            'seat' => $participant->seat,
            'delta' => $delta,
            'created_at' => now(),
        ]);
        if ($inserted === 0) {
            return;
        }

        DB::table('game_points')->where('id', $row->id)->update([
            'points' => (int) $row->points + $delta,
            'wins' => (int) $row->wins + ($won ? 1 : 0),
            'losses' => (int) $row->losses + ($won ? 0 : 1),
            'updated_at' => now(),
        ]);
        DB::afterCommit(fn () => Cache::forget($this->gameKey($game)));
    }

    /**
     * The game's Top 10: by points, or (Poker, Blackjack) by Fishkas.
     *
     * @return array{kind: string|null, rows: list<array<string, mixed>>}
     */
    public function forGame(Game $game): array
    {
        $kind = $this->kindFor($game);
        if ($kind === null) {
            return ['kind' => null, 'rows' => []];
        }
        if ($kind === 'chips') {
            return ['kind' => 'chips', 'rows' => $this->mostChips()];
        }

        return ['kind' => 'points', 'rows' => Cache::remember($this->gameKey($game), self::TTL, fn () => $this->points($game))];
    }

    /** @return list<array<string, mixed>> */
    public function highestLevels(): array
    {
        return Cache::remember('rankings:levels', self::TTL, fn () => $this->rank(
            $this->people()->orderByDesc('users.level')->orderByDesc('users.xp')->orderBy('users.id')
                ->limit(self::TOP)->get(['users.id', 'users.username', 'users.level', 'user_profiles.display_name', 'user_profiles.avatar_path', 'users.level as value']),
        ));
    }

    /** @return list<array<string, mixed>> */
    public function mostChips(): array
    {
        return Cache::remember('rankings:chips', self::TTL, fn () => $this->rank(
            $this->people()->join('fishka_wallets', 'fishka_wallets.user_id', '=', 'users.id')
                ->where('fishka_wallets.balance', '>', 0)
                ->orderByDesc('fishka_wallets.balance')->orderBy('users.id')
                ->limit(self::TOP)->get(['users.id', 'users.username', 'users.level', 'user_profiles.display_name', 'user_profiles.avatar_path', 'fishka_wallets.balance as value']),
        ));
    }

    /** @return list<array<string, mixed>> */
    private function points(Game $game): array
    {
        $rows = DB::table('game_points')
            ->leftJoin('users', 'users.id', '=', 'game_points.user_id')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->leftJoin('bot_profiles', 'bot_profiles.id', '=', 'game_points.bot_profile_id')
            ->where('game_points.game_id', $game->id)
            ->where('game_points.points', '>', 0)
            ->where(fn ($q) => $q->whereNull('game_points.user_id')->orWhere('users.status', UserStatus::Active->value))
            ->orderByDesc('game_points.points')->orderByDesc('game_points.wins')->orderBy('game_points.id')
            ->limit(self::TOP)
            ->get([
                'users.username', 'users.level', 'user_profiles.display_name', 'user_profiles.avatar_path',
                'bot_profiles.id as bot_id', 'bot_profiles.name as bot_name', 'bot_profiles.level as bot_level', 'bot_profiles.avatar_path as bot_avatar',
                'game_points.points as value',
            ]);

        return $this->rank($rows);
    }

    private function people(): Builder
    {
        return DB::table('users')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->where('users.status', UserStatus::Active->value);
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @return list<array<string, mixed>>
     */
    private function rank(Collection $rows): array
    {
        $url = fn (?string $path) => $path === null ? null : Storage::disk('public')->url($path);

        return $rows->values()->map(function (\stdClass $row, int $i) use ($url) {
            $ai = isset($row->bot_id);
            $level = (int) ($ai ? $row->bot_level : $row->level);

            return [
                'rank' => $i + 1,
                'is_ai' => $ai,
                'username' => $ai ? null : (string) $row->username,
                'name' => $ai ? (string) $row->bot_name : (string) ($row->display_name ?? $row->username),
                'avatar_url' => $url($ai ? $row->bot_avatar : $row->avatar_path),
                'level' => $level,
                'frame' => $this->levels->looks($level)['frame'],
                'value' => (int) $row->value,
            ];
        })->all();
    }

    private function gameKey(Game $game): string
    {
        return "rankings:game:{$game->id}";
    }
}
