<?php

namespace App\Gameplay\Lifecycle;

use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Enums\XpSource;
use App\Gameplay\Engine\Outcome;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Models\UserGameStat;
use App\Platform\Levels\Levels;
use App\Platform\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ends a room: results per seat, room status, players' statistics and XP. Called inside the transaction
 * that records the final move, so a game can never end twice.
 */
class GameFinisher
{
    public function __construct(
        private readonly Levels $levels,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  list<int>  $abandonedSeats  Seats that lost by timing out or leaving.
     */
    public function finish(GameRoom $room, Outcome $outcome, string $endReason, array $abandonedSeats = []): void
    {
        $finishedAt = now();

        foreach ($room->participants as $participant) {
            // Players who left a game that went on already have their result.
            if (! $participant->is_active && $participant->result !== null) {
                continue;
            }
            $result = match (true) {
                in_array($participant->seat, $abandonedSeats, true) => ParticipantResult::Abandoned,
                $outcome->isDraw() => ParticipantResult::Draw,
                in_array($participant->seat, $outcome->winners, true) => ParticipantResult::Win,
                default => ParticipantResult::Loss,
            };

            $participant->forceFill([
                'result' => $result,
                'score' => $outcome->scores[$participant->seat] ?? null,
                'is_active' => false,
            ])->save();

            if ($participant->user_id !== null) {
                $this->recordStats($participant, $room->game_id, $result);
                $this->awardXp($participant, $room, $result, $finishedAt->getTimestamp());
            }
        }

        $room->forceFill([
            'status' => RoomStatus::Finished,
            'end_reason' => $endReason,
            'finished_at' => $finishedAt,
        ])->save();

        Log::info('Game finished.', [
            'room' => $room->public_id,
            'game_id' => $room->game_id,
            'end_reason' => $endReason,
            'outcome' => $outcome->reason,
            'seconds' => $room->started_at?->diffInSeconds($finishedAt),
        ]);
    }

    /**
     * One player's game is over while it goes on for the others (they left a multi-player game).
     */
    public function finishParticipant(GameParticipant $participant, int $gameId, ParticipantResult $result): void
    {
        $participant->forceFill(['result' => $result, 'is_active' => false])->save();

        if ($participant->user_id !== null) {
            $this->recordStats($participant, $gameId, $result);
            $this->awardXp($participant, $participant->room, $result, now()->getTimestamp());
        }
    }

    /**
     * XP for a game a person finished (ADR 0018): for taking part, plus a win or a draw. Nothing for an
     * abandoned game or one shorter than the minimum (no farming by resigning at once). Once per room.
     */
    private function awardXp(GameParticipant $participant, GameRoom $room, ParticipantResult $result, int $finishedAt): void
    {
        if ($result === ParticipantResult::Abandoned || $participant->user_id === null) {
            return;
        }
        $seconds = $room->started_at === null ? 0 : $finishedAt - $room->started_at->getTimestamp();
        if ($seconds < (int) $this->settings->get('levels.min_game_seconds')) {
            return;
        }

        $xp = (int) $this->settings->get('levels.xp_game') + match ($result) {
            ParticipantResult::Win => (int) $this->settings->get('levels.xp_win'),
            ParticipantResult::Draw => (int) $this->settings->get('levels.xp_draw'),
            default => 0,
        };
        $this->levels->award($participant->user_id, $xp, XpSource::Game, "game:{$room->public_id}:{$participant->user_id}", 'game_room', $room->public_id);
    }

    private function recordStats(GameParticipant $participant, int $gameId, ParticipantResult $result): void
    {
        DB::table('user_game_stats')->insertOrIgnore([
            'user_id' => $participant->user_id,
            'game_id' => $gameId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var UserGameStat $stats */
        $stats = UserGameStat::query()
            ->where('user_id', $participant->user_id)
            ->where('game_id', $gameId)
            ->lockForUpdate()
            ->firstOrFail();

        $streak = $result === ParticipantResult::Win ? $stats->current_streak + 1 : 0;

        UserGameStat::query()
            ->where('user_id', $participant->user_id)
            ->where('game_id', $gameId)
            ->update([
                'played' => $stats->played + 1,
                'wins' => $stats->wins + ($result === ParticipantResult::Win ? 1 : 0),
                'losses' => $stats->losses + ($result === ParticipantResult::Loss ? 1 : 0),
                'draws' => $stats->draws + ($result === ParticipantResult::Draw ? 1 : 0),
                'abandoned' => $stats->abandoned + ($result === ParticipantResult::Abandoned ? 1 : 0),
                'current_streak' => $streak,
                'best_streak' => max($stats->best_streak, $streak),
                'last_played_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
