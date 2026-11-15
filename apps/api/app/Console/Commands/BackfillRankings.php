<?php

namespace App\Console\Commands;

use App\Enums\RoomStatus;
use App\Models\GameRoom;
use App\Platform\Rankings\Rankings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('rankings:backfill')]
#[Description('Score the finished games played before rankings existed (each game and seat counts once; safe to repeat)')]
class BackfillRankings extends Command
{
    public function handle(Rankings $rankings): int
    {
        $count = 0;
        GameRoom::query()->with(['game', 'participants'])
            ->where('status', RoomStatus::Finished)
            ->orderBy('finished_at')
            ->chunkById(200, function ($rooms) use ($rankings, &$count) {
                foreach ($rooms as $room) {
                    DB::transaction(function () use ($room, $rankings, &$count) {
                        foreach ($room->participants as $participant) {
                            if ($participant->result !== null) {
                                $rankings->record($room, $participant, $participant->result);
                            }
                        }
                        $count++;
                    });
                }
            });
        $this->info("Looked at {$count} finished game(s).");

        return self::SUCCESS;
    }
}
