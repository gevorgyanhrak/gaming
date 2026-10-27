<?php

namespace App\Gameplay\Lifecycle;

use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Gameplay\Engine\Outcome;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Models\UserGameStat;
use Illuminate\Support\Facades\DB;

/**
 * Ends a room: results per seat, room status, and players' statistics. Called inside the transaction
 * that records the final move, so a game can never end twice.
 */
class GameFinisher
{
    /**
     * @param  list<int>  $abandonedSeats  Seats that lost by timing out or leaving.
     */
    public function finish(GameRoom $room, Outcome $outcome, string $endReason, array $abandonedSeats = []): void
    {
        $finishedAt = now();

        foreach ($room->participants as $participant) {
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
            }
        }

        $room->forceFill([
            'status' => RoomStatus::Finished,
            'end_reason' => $endReason,
            'finished_at' => $finishedAt,
        ])->save();
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
