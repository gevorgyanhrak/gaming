<?php

namespace App\Http\Controllers\Api\V1;

use App\Gameplay\Matchmaking\Matchmaker;
use App\Gameplay\Matchmaking\Ticket;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Find an opponent. Responses carry either the waiting ticket or the room once matched; the
 * "matchmaking.matched" events arrive on the player's channel too.
 */
class MatchmakingController extends Controller
{
    public function store(Request $request, Matchmaker $matchmaker): JsonResponse
    {
        $request->validate([
            'game_mode_id' => ['required', 'integer', Rule::exists('game_modes', 'id')],
            // The play group to be matched in (default: the lowest). Matched tables play at its minimum bet.
            'group' => ['nullable', 'string', 'max:32'],
        ]);
        /** @var User $user */
        $user = $request->user();

        $result = $matchmaker->join(
            $user,
            GameMode::query()->with('game')->findOrFail($request->integer('game_mode_id')),
            $request->filled('group') ? $request->string('group')->toString() : null,
        );

        return $this->respond($matchmaker, $result instanceof GameRoom ? null : $result, $result instanceof GameRoom ? $result->public_id : null, 201);
    }

    public function show(Request $request, Matchmaker $matchmaker): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->respond($matchmaker, $matchmaker->ticket($user), $matchmaker->matchedRoom($user));
    }

    /** Called every ~15 s while the matchmaking screen is open. */
    public function heartbeat(Request $request, Matchmaker $matchmaker): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->respond($matchmaker, $matchmaker->heartbeat($user), $matchmaker->matchedRoom($user));
    }

    public function destroy(Request $request, Matchmaker $matchmaker): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $matchmaker->leave($user);

        return response()->json(null, 204);
    }

    private function respond(Matchmaker $matchmaker, ?Ticket $ticket, ?string $room, int $status = 200): JsonResponse
    {
        return response()->json(['data' => [
            /** @var array<string, mixed>|null */
            'ticket' => $ticket === null ? null : new TicketResource($ticket),
            /** Players in the same queue, the searching player included (null when not searching). */
            'waiting' => $ticket === null ? null : $matchmaker->waiting($ticket),
            /** @var string|null When the game starts: the countdown's end (null: waiting for people only). */
            'starts_at' => ($at = $ticket === null ? null : $matchmaker->startsAt($ticket)) === null ? null : now()->setTimestamp($at)->toIso8601String(),
            /**
             * The players at the table being prepared (everyone looks the same), or null while searching.
             *
             * @var list<array{name: string, avatar_url: string|null, level: int, is_bot: bool}>|null
             */
            'table' => $ticket === null ? null : $matchmaker->tablePlayers($ticket),
            /** @var string|null */
            'room' => $room,
        ]], $status);
    }
}
