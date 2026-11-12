<?php

namespace App\Platform\Games;

use App\Enums\RoomStatus;
use App\Gameplay\GameRegistry;
use App\Gameplay\Rooms\RoomStakes;
use App\Gameplay\Rooms\RoomStateStore;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use App\Platform\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Game Management (owner, 2026-11-12): one place to switch any game on or off, put it in maintenance, end
 * its games under way, and remove it — archived (hidden, history kept) or purged (its data deleted). Every
 * step is audited. Games under way are never touched unless endActiveGames() is asked for. The checks that
 * enforce the status live where games start (PlayRules) and in the catalog (Game::visible).
 */
class GameAdministration
{
    public function __construct(
        private readonly GameRegistry $registry,
        private readonly RoomStakes $stakes,
        private readonly RoomStateStore $store,
        private readonly AuditLogger $audit,
    ) {}

    /** Playable and listed again (needs the game's engine). */
    public function enable(Game $game, ?User $actor = null): void
    {
        if (! $this->registry->has($game->slug)) {
            throw new RuntimeException('This game has no engine yet, so it can\'t be enabled.');
        }
        $game->update(['is_enabled' => true, 'is_visible' => true, 'maintenance' => false, 'archived_at' => null]);
        $this->audit->record('game.enabled', $game, [], $actor);
    }

    /** Hidden from the catalog and lobby; no new games. Games under way go on. */
    public function disable(Game $game, ?User $actor = null): void
    {
        $game->update(['is_enabled' => false, 'is_visible' => false]);
        $this->audit->record('game.disabled', $game, [], $actor);
    }

    /**
     * Shown with a message, no new games. Games under way go on.
     *
     * @param  array<string, string>|null  $message  per language
     */
    public function maintenance(Game $game, bool $on, ?array $message = null, ?User $actor = null): void
    {
        $game->maintenance = $on;
        if ($message !== null) {
            $game->setTranslations('maintenance_message', array_filter($message, fn (?string $m) => $m !== null && $m !== ''));
        }
        $game->save();
        $this->audit->record($on ? 'game.maintenance_on' : 'game.maintenance_off', $game, [], $actor);
    }

    /** Games under way or waiting for players. */
    public function activeRooms(Game $game): int
    {
        return GameRoom::query()->where('game_id', $game->id)->whereIn('status', [RoomStatus::Active, RoomStatus::Waiting])->count();
    }

    /**
     * End every game under way (and close waiting tables) now: nobody wins, everyone's bet or buy-in goes
     * back. Only when the admin asks for it.
     */
    public function endActiveGames(Game $game, ?User $actor = null): int
    {
        $rooms = GameRoom::query()->where('game_id', $game->id)->whereIn('status', [RoomStatus::Active, RoomStatus::Waiting])
            ->with(['game', 'mode', 'participants.user'])->get();
        foreach ($rooms as $room) {
            $this->store->locked($room, function () use ($room) {
                DB::transaction(function () use ($room) {
                    foreach ($room->participants as $participant) {
                        $this->stakes->refund($room, $participant);
                    }
                    $room->participants()->update(['is_active' => false]);
                    $room->forceFill(['status' => RoomStatus::Aborted, 'end_reason' => 'admin', 'finished_at' => now()])->save();
                });
                $this->store->forget($room);
            });
        }
        $this->audit->record('game.active_games_ended', $game, ['rooms' => $rooms->count()], $actor);

        return $rooms->count();
    }

    /** Removed from the platform: hidden everywhere, its rooms, results and statistics kept. */
    public function archive(Game $game, ?User $actor = null): void
    {
        $game->update(['is_enabled' => false, 'is_visible' => false, 'archived_at' => now()]);
        $this->audit->record('game.archived', $game, [], $actor);
    }

    /**
     * Removed with its data: modes, rooms with their seats and moves, statistics, AI profiles for it. Refused
     * while games are under way. Other games and players' accounts are untouched.
     */
    public function purge(Game $game, ?User $actor = null): void
    {
        if ($this->activeRooms($game) > 0) {
            throw new RuntimeException('End the games under way first.');
        }
        $snapshot = ['slug' => $game->slug, 'name' => $game->getTranslations('name')];
        DB::transaction(function () use ($game) {
            $rooms = GameRoom::query()->where('game_id', $game->id)->pluck('id');
            foreach ($rooms->chunk(500) as $chunk) {
                DB::table('game_moves')->whereIn('game_room_id', $chunk)->delete();
                DB::table('game_participants')->whereIn('game_room_id', $chunk)->delete();
                DB::table('game_rooms')->whereIn('id', $chunk)->delete();
            }
            DB::table('user_game_stats')->where('game_id', $game->id)->delete();
            $game->botProfiles()->delete();
            $game->modes()->delete();
            $game->delete();
        });
        $this->audit->record('game.purged', null, $snapshot, $actor);
    }
}
