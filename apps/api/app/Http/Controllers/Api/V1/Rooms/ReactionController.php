<?php

namespace App\Http\Controllers\Api\V1\Rooms;

use App\Gameplay\Reactions\RoomReactions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\SendReactionRequest;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    /**
     * The room's emojis and messages after sequence number `after`, oldest first: the table chat's
     * history (the last 50, for up to 3 hours), and catch-up for pages without a live connection.
     */
    public function index(Request $request, GameRoom $room, RoomReactions $reactions): JsonResponse
    {
        $after = $request->query('after');

        return response()->json([
            /** @var list<array{id: string, seq: int, room: string, from_seat: int, to_seat: int|null, kind: string, value: string, sent_at: string}> */
            'data' => $reactions->since($room, is_numeric($after) ? (int) $after : null),
        ]);
    }

    /**
     * Send an emoji or a predefined message to one player at the table, or to everyone. 201 with the
     * reaction (also delivered to every player as `room.reaction`); a repeat of the same client_id
     * returns the first one and sends nothing. 403 if the sender has no seat or reactions are off; 409
     * when the room no longer takes them.
     */
    public function store(SendReactionRequest $request, GameRoom $room, RoomReactions $reactions): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $room->load('participants');
        $from = $room->participantFor($user);
        abort_if($from === null, 403);

        $toSeat = $request->input('to_seat');
        $reaction = $reactions->send(
            $room,
            $from,
            $request->string('kind')->toString(),
            $request->string('value')->toString(),
            $toSeat === null ? null : (int) $toSeat,
            $request->string('client_id')->toString(),
        );

        return response()->json(['data' => $reaction], 201);
    }
}
