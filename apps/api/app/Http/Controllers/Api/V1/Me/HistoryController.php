<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\GameStatResource;
use App\Http\Resources\RoomResource;
use App\Models\GameRoom;
use App\Models\User;
use App\Models\UserGameStat;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HistoryController extends Controller
{
    /**
     * Finished games, newest first (paginated). Optional ?game=<slug>.
     */
    public function games(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $rooms = GameRoom::query()
            ->where('status', RoomStatus::Finished)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->when($request->string('game')->toString(), fn ($q, $slug) => $q->whereHas('game', fn ($g) => $g->where('slug', $slug)))
            ->with(['game', 'mode', 'participants.user.profile', 'participants.bot'])
            ->latest('finished_at')
            ->paginate(20);

        return RoomResource::collection($rooms);
    }

    /**
     * Totals per game.
     */
    public function stats(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return GameStatResource::collection(
            UserGameStat::query()->where('user_id', $user->id)->with('game')->orderByDesc('played')->get(),
        );
    }
}
