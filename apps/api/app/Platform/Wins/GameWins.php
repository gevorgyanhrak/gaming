<?php

namespace App\Platform\Wins;

use App\Models\Game;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Platform\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Winnings from games, as the ledger recorded them: a person's net Fishkas for a room (entry fees, buy-ins, stakes,
 * payouts and cash-outs, all tagged with the room) once their game is over. Above zero it is a win — kept in
 * game_wins (once per room and person) and announced on the public "wins" channel; the biggest are celebrated.
 * Computed only by the server from its own ledger: nothing a client sends, never AI (it has no wallet).
 */
class GameWins
{
    public function __construct(private readonly Settings $settings) {}

    /** Records the person's win in the room, if they won Fishkas there (after the game's transaction commits). */
    public function record(GameRoom $room, GameParticipant $participant): void
    {
        if ($participant->user_id === null || $participant->bot_profile_id !== null) {
            return;
        }
        $amount = (int) DB::table('fishka_transactions')
            ->where('user_id', $participant->user_id)
            ->where('reference_type', 'game_room')
            ->where('reference_id', $room->public_id)
            ->sum('amount');
        if ($amount <= 0) {
            return;
        }
        $inserted = DB::table('game_wins')->insertOrIgnore([
            'user_id' => $participant->user_id,
            'game_id' => $room->game_id,
            'room_public_id' => $room->public_id,
            'amount' => $amount,
            'won_at' => now(),
        ]);
        if ($inserted === 0) {
            return;
        }
        $id = (int) DB::table('game_wins')->where('room_public_id', $room->public_id)->where('user_id', $participant->user_id)->value('id');
        WinRecorded::dispatch($this->present($id));
    }

    /** The amount from which a win is celebrated on every open page (admin setting). */
    public function celebrateAt(): int
    {
        return (int) $this->settings->get('wins.celebrate_at');
    }

    /**
     * The biggest wins of the last $days days, biggest first.
     *
     * @return list<array<string, mixed>>
     */
    public function biggest(int $days = 7, int $limit = 12): array
    {
        return array_map(fn (int $id) => $this->present($id), DB::table('game_wins')
            ->where('won_at', '>=', now()->subDays($days))
            ->orderByDesc('amount')->orderByDesc('won_at')
            ->limit($limit)->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    /**
     * One win as the feed shows it.
     *
     * @return array{id: int, amount: int, won_at: string, celebrate: bool, player: array{username: string, name: string, avatar_url: string|null, level: int}, game: array{slug: string, name: string}}
     */
    public function present(int $id): array
    {
        $win = DB::table('game_wins')
            ->join('users', 'users.id', '=', 'game_wins.user_id')
            ->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->join('games', 'games.id', '=', 'game_wins.game_id')
            ->where('game_wins.id', $id)
            ->first(['game_wins.id', 'game_wins.amount', 'game_wins.won_at', 'users.username', 'users.level', 'user_profiles.display_name', 'user_profiles.avatar_path', 'games.slug', 'games.name']);
        $game = Game::query()->where('slug', $win->slug)->first();

        return [
            'id' => (int) $win->id,
            'amount' => (int) $win->amount,
            'won_at' => Carbon::parse($win->won_at)->toIso8601String(),
            'celebrate' => (int) $win->amount >= $this->celebrateAt(),
            'player' => [
                'username' => (string) $win->username,
                'name' => (string) ($win->display_name ?? $win->username),
                'avatar_url' => $win->avatar_path === null ? null : Storage::disk('public')->url((string) $win->avatar_path),
                'level' => (int) $win->level,
            ],
            'game' => ['slug' => (string) $win->slug, 'name' => (string) ($game->name ?? $win->slug)],
        ];
    }
}
