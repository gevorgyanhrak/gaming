<?php

namespace App\Gameplay\Rooms;

use App\Enums\BotDifficulty;
use App\Enums\RoomStatus;
use App\Enums\RoomType;
use App\Gameplay\Actions\RoomEvents;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Lifecycle\GameFinisher;
use App\Gameplay\Lifecycle\GameScheduler;
use App\Gameplay\Lifecycle\RoomPresence;
use App\Gameplay\Lifecycle\TimeControl;
use App\Gameplay\Random\RecordingRandom;
use App\Models\BotProfile;
use App\Models\GameMode;
use App\Models\GameMove;
use App\Models\GameRoom;
use App\Models\User;
use App\Platform\Fishka\InsufficientFishkas;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates rooms, seats players and bots, and starts games.
 */
class RoomService
{
    public function __construct(
        private readonly RoomStateStore $store,
        private readonly RoomStates $states,
        private readonly GameScheduler $scheduler,
        private readonly RoomEvents $events,
        private readonly RoomStakes $stakes,
    ) {}

    /**
     * Create a room with every seat filled (matchmaking, bot games) and start it.
     *
     * @param  list<User|array{bot: BotProfile, difficulty: BotDifficulty}>  $players  In seat order.
     */
    public function createStarted(GameMode $mode, RoomType $type, array $players, ?User $creator = null): GameRoom
    {
        if (count($players) < $mode->min_players || count($players) > $mode->max_players) {
            throw new InvalidArgumentException('Wrong number of players for this mode.');
        }

        $room = DB::transaction(function () use ($mode, $type, $players, $creator) {
            $room = $this->createRoom($mode, $type, count($players), $creator);

            foreach ($players as $seat => $player) {
                $this->seat($room, $seat, $player);
            }

            return $room;
        });

        return $this->start($room);
    }

    /**
     * Create a room that waits for more players (private rooms); the creator takes seat 0.
     */
    public function createWaiting(GameMode $mode, User $creator, string $inviteCode, ?int $seats = null): GameRoom
    {
        return DB::transaction(function () use ($mode, $creator, $inviteCode, $seats) {
            $room = $this->createRoom($mode, RoomType::Private, $seats ?? $mode->max_players, $creator, $inviteCode);
            $this->seat($room, 0, $creator);

            return $room;
        });
    }

    /**
     * Take the next free seat in a waiting room; starts the game when the room is full.
     */
    public function join(GameRoom $room, User $user): GameRoom
    {
        return $this->store->locked($room, function () use ($room, $user) {
            $room->refresh()->load('participants');

            if ($room->status !== RoomStatus::Waiting) {
                throw new InvalidArgumentException('This room is no longer open.');
            }

            if ($room->participantFor($user) === null) {
                DB::transaction(fn () => $this->seat($room, $room->participants->count(), $user));
                $room->load('participants');
            }

            return $room->participants->count() >= $room->seats ? $this->start($room) : $room;
        });
    }

    public function start(GameRoom $room): GameRoom
    {
        $room->load(['game', 'mode', 'participants']);
        $engine = $this->states->engine($room);
        // Games played for chips start each person with their buy-in (kept in the options, so a replay
        // of the move log starts the same way).
        $stacks = $this->stakes->stacks($room);
        if ($stacks !== null) {
            $room->forceFill(['options' => [...($room->options ?? []), 'stacks' => $stacks]])->save();
        }
        $random = new RecordingRandom;
        $initial = $engine->initialState(new GameSetup($room->seats, $room->options ?? []), $random);

        $time = TimeControl::for($room);
        $nowMs = now()->getTimestampMs();
        $clock = $time->start($room->seats, $nowMs);

        // The deal can decide a game before anyone acts (poker: a player all-in on the blind for their
        // whole 1-Fishka buy-in loses or wins the first hand by itself).
        $outcome = $engine->outcome($initial);

        DB::transaction(function () use ($room, $random, $clock, $outcome, $initial) {
            $room->moves()->create(['seq' => 0, 'type' => GameMove::START, 'rng' => $random->draws() ?: null]);
            $room->forceFill(['status' => RoomStatus::Active, 'started_at' => now(), 'state_version' => 0, 'clock' => $clock])->save();

            if ($outcome !== null) {
                app(GameFinisher::class)->finish($room, $outcome, 'completed');
                $this->stakes->settle($room, $initial);
            }
        });

        $seats = $outcome === null ? $engine->seatsToAct($initial) : [];
        $state = new RoomState(0, $initial, $seats, $this->states->deadline($room, $initial, $seats, $nowMs), $clock);
        $this->store->put($room, $state);

        app(RoomPresence::class)->started($room);

        DB::afterCommit(function () use ($room, $state) {
            $this->events->changed($room, $state, [['type' => 'started']]);
            $this->scheduler->afterChange($room, $state);
        });

        return $room;
    }

    private function createRoom(GameMode $mode, RoomType $type, int $seats, ?User $creator, ?string $inviteCode = null): GameRoom
    {
        return GameRoom::query()->create([
            'game_id' => $mode->game_id,
            'game_mode_id' => $mode->id,
            'type' => $type,
            'status' => RoomStatus::Waiting,
            'invite_code' => $inviteCode,
            'created_by' => $creator?->id,
            'seats' => $seats,
            'options' => $mode->config ?? [],
        ]);
    }

    /**
     * Seat a person (who pays the seat's Fishkas, see RoomStakes) or a bot. Call inside a database
     * transaction, so a seat that can't be paid for is never taken.
     *
     * @param  User|array{bot: BotProfile, difficulty: BotDifficulty}  $player
     *
     * @throws InsufficientFishkas
     */
    private function seat(GameRoom $room, int $seat, User|array $player): void
    {
        if ($player instanceof User) {
            $participant = $room->participants()->create(['seat' => $seat, 'user_id' => $player->id]);
            $this->stakes->take($room, $participant, $player);

            return;
        }

        $room->participants()->create(['seat' => $seat, 'bot_profile_id' => $player['bot']->id, 'bot_difficulty' => $player['difficulty']]);
        $room->forceFill(['has_bot' => true])->save();
    }
}
