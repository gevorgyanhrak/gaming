<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BotDifficulty;
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
use Illuminate\Validation\ValidationException;

/**
 * Find an opponent. Responses carry either the waiting ticket or the room once matched; the
 * "matchmaking.matched" / "matchmaking.bot_offered" events arrive on the player's channel too.
 */
class MatchmakingController extends Controller
{
    public function store(Request $request, Matchmaker $matchmaker): JsonResponse
    {
        $request->validate(['game_mode_id' => ['required', 'integer', Rule::exists('game_modes', 'id')]]);
        /** @var User $user */
        $user = $request->user();

        $result = $matchmaker->join($user, GameMode::query()->with('game')->findOrFail($request->integer('game_mode_id')));

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

    public function acceptBot(Request $request, Matchmaker $matchmaker): JsonResponse
    {
        $request->validate(['bot_difficulty' => ['sometimes', Rule::enum(BotDifficulty::class)]]);
        /** @var User $user */
        $user = $request->user();

        $room = $matchmaker->acceptBot($user, BotDifficulty::tryFrom($request->string('bot_difficulty')->toString()) ?? BotDifficulty::Medium);

        if ($room === null) {
            throw ValidationException::withMessages(['ticket' => __('platform.play.no_bot_offer')]);
        }

        return $this->respond($matchmaker, null, $room->public_id, 201);
    }

    private function respond(Matchmaker $matchmaker, ?Ticket $ticket, ?string $room, int $status = 200): JsonResponse
    {
        return response()->json(['data' => [
            /** @var array<string, mixed>|null */
            'ticket' => $ticket === null ? null : new TicketResource($ticket),
            /** Players in the same queue, the searching player included (null when not searching). */
            'waiting' => $ticket === null ? null : $matchmaker->waiting($ticket->modeId),
            /** @var string|null */
            'room' => $room,
        ]], $status);
    }
}
