<?php

namespace App\Http\Controllers\Api\V1\Rooms;

use App\Enums\BotDifficulty;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Gameplay\Actions\GameActionService;
use App\Gameplay\Bots\BotSelector;
use App\Gameplay\Engine\Action;
use App\Gameplay\Rooms\PlayRules;
use App\Gameplay\Rooms\RoomPresenter;
use App\Gameplay\Rooms\RoomService;
use App\Gameplay\Rooms\RoomStates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rooms\CreateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RoomController extends Controller
{
    private const INVITE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Start a game against a bot, or open a private room to share by invite code.
     */
    public function store(CreateRoomRequest $request, PlayRules $rules, RoomService $rooms, BotSelector $bots): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $mode = GameMode::query()->with('game')->findOrFail($request->integer('game_mode_id'));
        $type = $request->string('type')->toString();

        $rules->ensureCanStart($user, $mode, $type);

        if ($type === 'bot') {
            $difficulty = BotDifficulty::from($request->string('bot_difficulty')->toString());

            try {
                $bot = $bots->pick($mode->game, $difficulty);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['bot_difficulty' => __('platform.play.no_bot')]);
            }

            $room = $rooms->createStarted($mode, RoomType::Bot, [$user, ['bot' => $bot, 'difficulty' => $difficulty]], $user);
        } else {
            $room = $rooms->createWaiting($mode, $user, $this->inviteCode());
        }

        return RoomResource::make($this->loaded($room))->response()->setStatusCode(201);
    }

    /**
     * Join a private room with its invite code. The game starts when the room is full.
     */
    public function join(Request $request, PlayRules $rules, RoomService $rooms): RoomResource
    {
        $request->validate(['invite_code' => ['required', 'string', 'max:12']]);
        /** @var User $user */
        $user = $request->user();

        $room = GameRoom::query()->with('mode.game')
            ->where('invite_code', strtoupper($request->string('invite_code')->trim()->toString()))
            ->where('status', RoomStatus::Waiting)
            ->first();

        if ($room === null) {
            throw ValidationException::withMessages(['invite_code' => __('platform.play.invite_invalid')]);
        }

        if ($room->participantFor($user) === null) {
            $rules->ensureCanStart($user, $room->mode, 'private');
        }

        try {
            $room = $rooms->join($room, $user);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['invite_code' => __('platform.play.invite_invalid')]);
        }

        return new RoomResource($this->loaded($room));
    }

    public function show(GameRoom $room): RoomResource
    {
        return new RoomResource($this->loaded($room));
    }

    /**
     * The game as the signed-in player sees it: their view, legal actions, deadline and version.
     * Clients call this on load and whenever an event's version shows they missed an update.
     */
    public function state(Request $request, GameRoom $room, RoomStates $states, RoomPresenter $presenter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $room->load(['game', 'participants']);

        if ($room->status === RoomStatus::Waiting || $room->status === RoomStatus::Aborted) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $presenter->stateFor($room, $states->current($room), $room->participantFor($user))]);
    }

    /**
     * Leave a room: resigns an active game, or leaves (the creator: closes) a waiting room.
     */
    public function leave(Request $request, GameRoom $room, GameActionService $actions): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $room->load(['game', 'participants']);
        $participant = $room->participantFor($user);

        if ($participant !== null && $room->isActive()) {
            $actions->submit($room, $participant, new Action('resign'), clientActionId: (string) Str::uuid());
        } elseif ($participant !== null && $room->status === RoomStatus::Waiting) {
            if ($room->created_by === $user->id) {
                $room->participants()->update(['is_active' => false]);
                $room->forceFill(['status' => RoomStatus::Aborted, 'finished_at' => now()])->save();
            } else {
                $participant->delete();
            }
        }

        return response()->json(['data' => new RoomResource($this->loaded($room->refresh()))]);
    }

    private function loaded(GameRoom $room): GameRoom
    {
        return $room->load(['game', 'mode', 'participants.user.profile', 'participants.bot']);
    }

    private function inviteCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::INVITE_ALPHABET[random_int(0, strlen(self::INVITE_ALPHABET) - 1)];
            }
        } while (GameRoom::query()->where('invite_code', $code)->exists());

        return $code;
    }
}
