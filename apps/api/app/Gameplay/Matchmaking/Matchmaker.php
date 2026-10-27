<?php

namespace App\Gameplay\Matchmaking;

use App\Enums\BotDifficulty;
use App\Enums\BotFallbackMode;
use App\Enums\RoomType;
use App\Gameplay\Bots\BotSelector;
use App\Gameplay\Rooms\PlayRules;
use App\Gameplay\Rooms\RoomService;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\User;
use App\Platform\Settings\Settings;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Pairs waiting players per game mode, and falls back to bots when nobody is found in time.
 *
 * Queues live in the game store; every change to a mode's queue happens under that mode's lock, so a
 * ticket can be matched only once (e.g. a human match and a bot acceptance can't both win).
 * Rooms are formed from min_players tickets; filling larger tables (poker) comes with those games.
 */
class Matchmaker
{
    /** Tickets whose browser stopped sending heartbeats for this long are dropped. */
    public const HEARTBEAT_TIMEOUT = 45;

    public function __construct(
        private readonly RoomService $rooms,
        private readonly PlayRules $rules,
        private readonly BotSelector $bots,
        private readonly Settings $settings,
    ) {}

    /**
     * Queue the player (leaving any other queue) and match immediately if possible.
     *
     * @return Ticket|GameRoom The ticket while waiting, or the room if matched right away.
     */
    public function join(User $user, GameMode $mode): Ticket|GameRoom
    {
        $this->rules->ensureCanStart($user, $mode, 'matchmaking');
        $this->leave($user);

        $now = now()->getTimestamp();
        $ticket = new Ticket($user->id, $mode->id, $now, $this->botAt($mode, $now), false, $now);

        $room = $this->withQueue($mode->id, function (array $queue) use ($ticket, $mode) {
            $queue[$ticket->userId] = $ticket;

            return $this->match($mode, $queue);
        });
        $this->store()->forever($this->userKey($user->id), $mode->id);
        $this->store()->forget($this->matchedKey($user->id));

        return $room instanceof GameRoom && $room->participantFor($user) !== null ? $room : $ticket;
    }

    public function leave(User $user): void
    {
        $modeId = $this->store()->get($this->userKey($user->id));

        if (is_int($modeId)) {
            $this->withQueue($modeId, function (array $queue) use ($user) {
                unset($queue[$user->id]);

                return [$queue, null];
            });
        }

        $this->store()->forget($this->userKey($user->id));
    }

    public function ticket(User $user): ?Ticket
    {
        $modeId = $this->store()->get($this->userKey($user->id));

        return is_int($modeId) ? ($this->queue($modeId)[$user->id] ?? null) : null;
    }

    /** The room a recent match placed the player in (for clients that missed the event). */
    public function matchedRoom(User $user): ?string
    {
        $room = $this->store()->get($this->matchedKey($user->id));

        return is_string($room) ? $room : null;
    }

    public function heartbeat(User $user): ?Ticket
    {
        $modeId = $this->store()->get($this->userKey($user->id));

        if (! is_int($modeId)) {
            return null;
        }

        $this->withQueue($modeId, function (array $queue) use ($user) {
            if (isset($queue[$user->id])) {
                $queue[$user->id] = $queue[$user->id]->with(heartbeatAt: now()->getTimestamp());
            }

            return [$queue, null];
        });

        return $this->ticket($user);
    }

    /**
     * Accept the offered bot game. Fails if the ticket was matched with a person in the meantime.
     */
    public function acceptBot(User $user, BotDifficulty $difficulty): ?GameRoom
    {
        $modeId = $this->store()->get($this->userKey($user->id));
        $mode = is_int($modeId) ? GameMode::query()->with('game')->find($modeId) : null;

        if ($mode === null) {
            return null;
        }

        $room = $this->withQueue($mode->id, function (array $queue) use ($user, $mode, $difficulty) {
            $ticket = $queue[$user->id] ?? null;

            if ($ticket === null || ! $ticket->botOffered) {
                return [$queue, null];
            }

            unset($queue[$user->id]);

            return [$queue, $this->botRoom($mode, $user, $difficulty)];
        });

        if ($room !== null) {
            $this->store()->forget($this->userKey($user->id));
        }

        return $room;
    }

    /**
     * Drop abandoned tickets, form rooms, and offer or start bot games when the timeout has passed.
     * Run every few seconds by the scheduler.
     */
    public function sweep(): void
    {
        foreach ($this->store()->get('mm:modes', []) as $modeId) {
            $mode = GameMode::query()->with('game')->find($modeId);

            if ($mode === null) {
                continue;
            }

            $this->withQueue($mode->id, function (array $queue) use ($mode) {
                $now = now()->getTimestamp();

                foreach ($queue as $userId => $ticket) {
                    if ($ticket->heartbeatAt < $now - self::HEARTBEAT_TIMEOUT) {
                        unset($queue[$userId]);
                        $this->store()->forget($this->userKey($userId));
                    }
                }

                [$queue] = $this->match($mode, $queue);

                foreach ($queue as $userId => $ticket) {
                    if ($ticket->botAt === null || $ticket->botAt > $now || $ticket->botOffered) {
                        continue;
                    }

                    if ($mode->game->bot_fallback_mode === BotFallbackMode::Auto) {
                        $user = User::query()->find($userId);
                        $room = $user === null ? null : $this->botRoom($mode, $user, BotDifficulty::Medium);
                        unset($queue[$userId]);
                        $this->store()->forget($this->userKey($userId));
                        if ($room !== null) {
                            $this->notifyMatched($room);
                        }
                    } else {
                        $queue[$userId] = $ticket->with(botOffered: true);
                        $this->notify($userId, 'bot_offered', ['mode_id' => $mode->id]);
                    }
                }

                return [$queue, null];
            });
        }
    }

    /**
     * Form as many rooms as the queue allows, oldest tickets first.
     *
     * @param  array<int, Ticket>  $queue
     * @return array{0: array<int, Ticket>, 1: GameRoom|null} Remaining queue and the last room formed.
     */
    private function match(GameMode $mode, array $queue): array
    {
        $room = null;
        uasort($queue, fn (Ticket $a, Ticket $b) => $a->joinedAt <=> $b->joinedAt);

        while (count($queue) >= $mode->min_players) {
            $tickets = array_slice($queue, 0, $mode->min_players, true);
            $players = User::query()->whereIn('id', array_keys($tickets))->get()->sortBy(fn (User $u) => $tickets[$u->id]->joinedAt)->values()->all();

            foreach (array_keys($tickets) as $userId) {
                unset($queue[$userId]);
                $this->store()->forget($this->userKey($userId));
            }

            if (count($players) < $mode->min_players) {
                continue;
            }

            $room = $this->rooms->createStarted($mode, RoomType::Matchmaking, $players);
            $this->notifyMatched($room);
        }

        return [$queue, $room];
    }

    private function botRoom(GameMode $mode, User $user, BotDifficulty $difficulty): ?GameRoom
    {
        try {
            $bot = $this->bots->pick($mode->game, $difficulty);
        } catch (Throwable) {
            $available = $this->bots->difficulties($mode->game);
            if ($available === []) {
                return null;
            }
            $bot = $this->bots->pick($mode->game, $difficulty = $available[0]);
        }

        return $this->rooms->createStarted($mode, RoomType::Bot, [$user, ['bot' => $bot, 'difficulty' => $difficulty]], $user);
    }

    private function botAt(GameMode $mode, int $now): ?int
    {
        $game = $mode->game;

        if (! $game->bots_enabled || ! $mode->bots_allowed || $game->bot_fallback_mode === BotFallbackMode::Off) {
            return null;
        }

        return $now + ($game->bot_fallback_timeout_seconds ?? (int) $this->settings->get('matchmaking.default_bot_timeout_seconds'));
    }

    private function notifyMatched(GameRoom $room): void
    {
        foreach ($room->participants as $participant) {
            if ($participant->user_id !== null) {
                $this->store()->put($this->matchedKey($participant->user_id), $room->public_id, 120);
                $this->notify($participant->user_id, 'matched', ['room' => $room->public_id]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function notify(int $userId, string $name, array $payload): void
    {
        try {
            event(new MatchmakingEvent($userId, $name, $payload));
        } catch (Throwable $e) {
            Log::warning('Matchmaking broadcast failed.', ['user' => $userId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @template T
     *
     * @param  Closure(array<int, Ticket>): array{0: array<int, Ticket>, 1: T}  $change
     * @return T
     */
    private function withQueue(int $modeId, Closure $change): mixed
    {
        $store = $this->store()->getStore();
        if (! $store instanceof LockProvider) {
            throw new LogicException('The game state store must support locks (use Redis).');
        }

        return $store->lock("mm-lock:{$modeId}", 10)->block(5, function () use ($modeId, $change) {
            [$queue, $result] = $change($this->queue($modeId));

            $this->store()->forever("mm:queue:{$modeId}", array_map(fn (Ticket $t) => $t->toArray(), $queue));
            $this->trackMode($modeId, $queue !== []);

            return $result;
        });
    }

    /**
     * @return array<int, Ticket>
     */
    private function queue(int $modeId): array
    {
        return array_map(fn (array $t) => Ticket::fromArray($t), $this->store()->get("mm:queue:{$modeId}", []));
    }

    /** Keeps the list of modes with waiting players (what sweep() visits), under its own lock. */
    private function trackMode(int $modeId, bool $hasTickets): void
    {
        /** @var LockProvider $store */
        $store = $this->store()->getStore();

        $store->lock('mm-lock:modes', 5)->block(5, function () use ($modeId, $hasTickets) {
            $modes = $this->store()->get('mm:modes', []);
            $modes = $hasTickets ? array_values(array_unique([...$modes, $modeId])) : array_values(array_diff($modes, [$modeId]));
            $this->store()->forever('mm:modes', $modes);
        });
    }

    private function userKey(int $userId): string
    {
        return "mm:user:{$userId}";
    }

    private function matchedKey(int $userId): string
    {
        return "mm:matched:{$userId}";
    }

    private function store(): Repository
    {
        return Cache::store(config('gameplay.state_store'));
    }
}
