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
 * Computed only by the server from its own ledger: nothing a client sends.
 *
 * AI players' wins in real games (owner 2026-11-15) are kept apart in ai_wins and shown in the feed marked AI
 * (`is_ai`), never celebrated and never on leaderboards or achievements. Nothing here is invented: no game, no win.
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
        // Kept for leaderboards and achievements either way; the Big Wins feed announces only big ones.
        if ($amount >= $this->feedMin()) {
            WinRecorded::dispatch($this->present($id));
        }
    }

    /**
     * Records what AI seats won in a finished room (RoomStakes::aiWinnings) and announces each, marked AI.
     *
     * @param  array<int, array{bot_profile_id: int, amount: int}>  $bySeat
     */
    public function recordAi(GameRoom $room, array $bySeat): void
    {
        foreach ($bySeat as $seat => $win) {
            $inserted = DB::table('ai_wins')->insertOrIgnore([
                'bot_profile_id' => $win['bot_profile_id'],
                'game_id' => $room->game_id,
                'room_public_id' => $room->public_id,
                'seat' => $seat,
                'amount' => $win['amount'],
                'won_at' => now(),
            ]);
            if ($inserted === 0) {
                continue;
            }
            $id = (int) DB::table('ai_wins')->where('room_public_id', $room->public_id)->where('seat', $seat)->value('id');
            if ($win['amount'] >= $this->feedMin()) {
                WinRecorded::dispatch($this->presentAi($id));
            }
        }
    }

    /** The amount from which a win is celebrated on every open page (admin setting). */
    public function celebrateAt(): int
    {
        return (int) $this->settings->get('wins.celebrate_at');
    }

    /** The smallest win the Big Wins feed shows (admin setting, 100,000 by default; HayBet item 16). */
    public function feedMin(): int
    {
        return (int) $this->settings->get('wins.feed_min');
    }

    /**
     * The latest big wins (at least feedMin) of the last $days days, people's and AI's together, newest first (the
     * live feed). Real records only: with no big win, the feed is empty.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $days = 30, int $limit = 15): array
    {
        $since = now()->subDays($days);
        $min = $this->feedMin();
        $people = DB::table('game_wins')->where('won_at', '>=', $since)->where('amount', '>=', $min)
            ->orderByDesc('won_at')->limit($limit)->get(['id', 'won_at'])
            ->map(fn ($w) => ['ai' => false, 'id' => (int) $w->id, 'at' => (string) $w->won_at]);
        $ai = DB::table('ai_wins')->where('won_at', '>=', $since)->where('amount', '>=', $min)
            ->orderByDesc('won_at')->limit($limit)->get(['id', 'won_at'])
            ->map(fn ($w) => ['ai' => true, 'id' => (int) $w->id, 'at' => (string) $w->won_at]);

        return $people->concat($ai)
            ->sortByDesc(fn (array $w) => $w['at'].sprintf('%012d', $w['id']))
            ->take($limit)
            ->map(fn (array $w) => $w['ai'] ? $this->presentAi($w['id']) : $this->present($w['id']))
            ->values()->all();
    }

    /**
     * One win as the feed shows it.
     *
     * @return array{id: string, amount: int, won_at: string, celebrate: bool, is_ai: bool, player: array{username: string|null, name: string, avatar_url: string|null, level: int}, game: array{slug: string, name: string}}
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
            'id' => 'p'.$win->id,
            'amount' => (int) $win->amount,
            'won_at' => Carbon::parse($win->won_at)->toIso8601String(),
            'celebrate' => (int) $win->amount >= $this->celebrateAt(),
            'is_ai' => false,
            'player' => [
                'username' => (string) $win->username,
                'name' => (string) ($win->display_name ?? $win->username),
                'avatar_url' => $win->avatar_path === null ? null : Storage::disk('public')->url((string) $win->avatar_path),
                'level' => (int) $win->level,
            ],
            'game' => ['slug' => (string) $win->slug, 'name' => (string) ($game->name ?? $win->slug)],
        ];
    }

    /**
     * An AI player's win as the feed shows it: marked AI, never celebrated, no profile link.
     *
     * @return array{id: string, amount: int, won_at: string, celebrate: bool, is_ai: bool, player: array{username: string|null, name: string, avatar_url: string|null, level: int}, game: array{slug: string, name: string}}
     */
    public function presentAi(int $id): array
    {
        $win = DB::table('ai_wins')
            ->join('bot_profiles', 'bot_profiles.id', '=', 'ai_wins.bot_profile_id')
            ->join('games', 'games.id', '=', 'ai_wins.game_id')
            ->where('ai_wins.id', $id)
            ->first(['ai_wins.id', 'ai_wins.amount', 'ai_wins.won_at', 'bot_profiles.name as bot', 'bot_profiles.level', 'bot_profiles.avatar_path', 'games.slug', 'games.name']);
        $game = Game::query()->where('slug', $win->slug)->first();

        return [
            'id' => 'a'.$win->id,
            'amount' => (int) $win->amount,
            'won_at' => Carbon::parse($win->won_at)->toIso8601String(),
            'celebrate' => false,
            'is_ai' => true,
            'player' => [
                'username' => null,
                'name' => (string) $win->bot,
                'avatar_url' => $win->avatar_path === null ? null : Storage::disk('public')->url((string) $win->avatar_path),
                'level' => (int) $win->level,
            ],
            'game' => ['slug' => (string) $win->slug, 'name' => (string) ($game->name ?? $win->slug)],
        ];
    }
}
