<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoomsController extends Controller
{
    /**
     * Rooms the player is in right now (waiting or in progress), to resume after a reload.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $rooms = GameRoom::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id)->where('is_active', true))
            ->with(['game', 'mode', 'participants.user.profile', 'participants.bot'])
            ->latest('id')
            ->get();

        return RoomResource::collection($rooms);
    }
}
