<?php

namespace App\Http\Controllers\Api\V1\Rooms;

use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Rooms\RoomPresenter;
use App\Gameplay\Rooms\RoomStates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\SubmitActionRequest;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ActionController extends Controller
{
    /**
     * Make a move. 200 with the new state; 409 if the client's view is outdated or the game is over;
     * 422 if the move is not legal now.
     */
    public function store(SubmitActionRequest $request, GameRoom $room, GameActionService $actions, RoomStates $states, RoomPresenter $presenter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $room->load(['game', 'participants']);
        $participant = $room->participantFor($user);
        abort_if($participant === null, 403);

        $actions->submit(
            $room,
            $participant,
            $request->action(),
            expectedVersion: $request->integer('expected_version'),
            clientActionId: $request->string('client_action_id')->toString(),
        );

        $room->refresh()->load(['game', 'participants']);

        return response()->json(['data' => $presenter->stateFor($room, $states->current($room), $participant)]);
    }
}
