<?php

namespace App\Gameplay\Rooms;

use App\Gameplay\Contracts\GameEngine;
use App\Gameplay\Contracts\HandlesDepartures;
use App\Gameplay\Contracts\PausesBetweenRounds;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\GameRegistry;
use App\Gameplay\Lifecycle\TimeControl;
use App\Gameplay\Random\ReplayRandom;
use App\Models\GameMove;
use App\Models\GameRoom;

/**
 * The current state of a room: from the store when it is up to date, otherwise rebuilt by replaying
 * the move log with the random numbers recorded at the time.
 */
class RoomStates
{
    public function __construct(
        private readonly RoomStateStore $store,
        private readonly GameRegistry $registry,
    ) {}

    public function engine(GameRoom $room): GameEngine
    {
        return $this->registry->get($room->game->slug)->engine();
    }

    public function current(GameRoom $room): RoomState
    {
        $stored = $this->store->get($room);

        if ($stored !== null && $stored->version === $room->state_version) {
            return $stored;
        }

        $state = $this->rebuild($room);
        $this->store->put($room, $state);

        return $state;
    }

    public function rebuild(GameRoom $room): RoomState
    {
        $engine = $this->engine($room);
        $moves = $room->moves()->with('participant')->get();

        /** @var GameMove $start */
        $start = $moves->firstWhere('seq', 0);
        $state = $engine->initialState(new GameSetup($room->seats, $room->options ?? []), new ReplayRandom($start->rng ?? []));
        $version = 0;

        foreach ($moves->where('seq', '>', 0) as $move) {
            $version = $move->seq;

            if ($move->type === GameMove::RESUME && $engine instanceof PausesBetweenRounds) {
                $state = $engine->resume($state, new ReplayRandom($move->rng ?? []))->state;

                continue;
            }
            if ($move->participant === null) {
                continue;
            }
            if (in_array($move->type, [GameMove::RESIGN, GameMove::ABANDON], true) && ($move->payload['departed'] ?? false) && $engine instanceof HandlesDepartures) {
                $state = $engine->depart($state, $move->participant->seat, new ReplayRandom($move->rng ?? []))->state;

                continue;
            }
            if (in_array($move->type, [GameMove::RESIGN, GameMove::TIMEOUT, GameMove::ABANDON], true)) {
                continue;
            }

            $state = $engine->apply($state, $move->participant->seat, Action::fromArray($move->payload ?? []), new ReplayRandom($move->rng ?? []))->state;
        }

        $seats = $room->isActive() ? $engine->seatsToAct($state) : [];

        // Chess clocks are stored on the room. Per-turn deadlines aren't: a rebuilt room gives the
        // seats to act a full turn (and a pause its full length).
        return new RoomState($version, $state, $seats, $this->deadline($room, $state, $seats, now()->getTimestampMs()), $room->clock);
    }

    /**
     * Unix time by which the seats to act must move or, while the game pauses between rounds,
     * when the next round starts.
     *
     * @param  array<string, mixed>  $state
     * @param  list<int>  $seats
     */
    public function deadline(GameRoom $room, array $state, array $seats, int $nowMs): ?int
    {
        $pause = $this->pauseMs($room, $state, $seats);

        return $pause === null
            ? TimeControl::for($room)->deadline($room->clock, $seats, $nowMs)
            : (int) ceil(($nowMs + $pause) / 1000);
    }

    /**
     * How long an active room waits before its next round, or null when it isn't paused.
     *
     * @param  array<string, mixed>  $state
     * @param  list<int>  $seats
     */
    public function pauseMs(GameRoom $room, array $state, array $seats): ?int
    {
        $engine = $this->engine($room);

        return $room->isActive() && $seats === [] && $engine instanceof PausesBetweenRounds ? $engine->pauseMs($state) : null;
    }
}
