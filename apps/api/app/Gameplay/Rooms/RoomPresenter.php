<?php

namespace App\Gameplay\Rooms;

use App\Models\GameParticipant;
use App\Models\GameRoom;

/**
 * The state of a room as one player sees it: used by GET /rooms/{room}/state and by RoomUpdated.
 */
class RoomPresenter
{
    public function __construct(private readonly RoomStates $states) {}

    /**
     * @return array<string, mixed>
     */
    public function stateFor(GameRoom $room, RoomState $state, ?GameParticipant $viewer): array
    {
        $engine = $this->states->engine($room);
        $seat = $viewer?->seat;
        $canAct = $seat !== null && in_array($seat, $state->seatsToAct, true);

        return [
            'room' => $room->public_id,
            'status' => $room->status->value,
            'version' => $state->version,
            'seat' => $seat,
            'seats_to_act' => $state->seatsToAct,
            'deadline' => $state->deadline === null ? null : now()->setTimestamp($state->deadline)->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'view' => $engine->viewFor($state->state, $seat),
            'legal_actions' => $canAct
                ? array_map(fn ($action) => $action->toArray(), $engine->legalActions($state->state, $seat))
                : [],
            'result' => $room->isActive() ? null : [
                'end_reason' => $room->end_reason,
                'seats' => $room->participants->mapWithKeys(fn (GameParticipant $p) => [$p->seat => $p->result?->value])->all(),
            ],
        ];
    }
}
