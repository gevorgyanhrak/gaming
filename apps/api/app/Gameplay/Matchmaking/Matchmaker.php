<?php

namespace App\Gameplay\Matchmaking;

use App\Enums\BotFallbackMode;
use App\Enums\PlayGroupKind;
use App\Enums\RoomType;
use App\Gameplay\Bots\AiPlayers;
use App\Gameplay\Rooms\PlayGroups;
use App\Gameplay\Rooms\PlayRules;
use App\Gameplay\Rooms\RoomService;
use App\Gameplay\Rooms\RoomStakes;
use App\Gameplay\Rooms\TableTerms;
use App\Models\GameMode;
use App\Models\GameRoom;
use App\Models\PlayGroup;
use App\Models\User;
use App\Platform\Fishka\InsufficientFishkas;
use App\Platform\Settings\Settings;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Pairs waiting players per game mode; people first, then AI fills the empty seats (fillWithAi).
 *
 * Queues live in the game store; every change to a mode's queue happens under that mode's lock, so a
 * ticket can be matched only once (e.g. a human match and a bot acceptance can't both win).
 * Two-player modes pair the two oldest tickets. Modes for bigger tables (poker) start a full table at
 * once, or sit down everyone waiting (at least min_players) once the oldest ticket has waited the mode's
 * `table_wait_seconds`. After the game's AI timeout, AI fills a table of BOT_TABLE_SIZE (two-player modes: one).
 */
class Matchmaker
{
    /** Tickets whose browser stopped sending heartbeats for this long are dropped. */
    public const HEARTBEAT_TIMEOUT = 45;

    /** How long a bigger table waits for more players when the mode doesn't say. */
    public const TABLE_WAIT_SECONDS = 20;

    /** Seats at a table AI fills, in modes for more than two players. */
    public const BOT_TABLE_SIZE = 4;

    public function __construct(
        private readonly RoomService $rooms,
        private readonly PlayRules $rules,
        private readonly AiPlayers $ai,
        private readonly Settings $settings,
        private readonly RoomStakes $stakes,
        private readonly PlayGroups $groups,
    ) {}

    /** The queue key of a mode and play group. */
    public static function pool(int $modeId, ?int $groupId): string
    {
        return "{$modeId}:".($groupId ?? '');
    }

    /**
     * The mode and terms of a queue: matched tables play at the group's minimum bet.
     *
     * @return array{0: GameMode, 1: TableTerms|null}|null
     */
    private function poolTerms(string $pool): ?array
    {
        [$modeId, $groupId] = array_pad(explode(':', $pool, 2), 2, '');
        $mode = GameMode::query()->with('game')->find((int) $modeId);
        if ($mode === null) {
            return null;
        }
        $group = $groupId === '' ? null : PlayGroup::query()->find((int) $groupId);

        return [$mode, $group === null ? null : new TableTerms($group, $group->kind === PlayGroupKind::Chips ? 0 : $group->min_bet)];
    }

    /**
     * Queue the player (leaving any other queue) and match immediately if possible.
     *
     * @return Ticket|GameRoom The ticket while waiting, or the room if matched right away.
     */
    public function join(User $user, GameMode $mode, ?string $group = null): Ticket|GameRoom
    {
        return $this->rules->exclusively($user, fn () => $this->queueUp($user, $mode, $group));
    }

    private function queueUp(User $user, GameMode $mode, ?string $group): Ticket|GameRoom
    {
        $terms = $this->groups->terms($mode, $group, null, 'matchmaking');
        $this->rules->ensureCanStart($user, $mode, 'matchmaking', $terms);
        $this->leave($user);

        $now = now()->getTimestamp();
        $ticket = new Ticket($user->id, $mode->id, $now, $this->botAt($mode, $now), $now, $terms->group->id);

        $room = $this->withQueue($ticket->pool(), function (array $queue) use ($ticket, $mode, $terms) {
            $queue[$ticket->userId] = $ticket;

            return $this->match($mode, $queue, $terms);
        });
        $this->store()->forever($this->userKey($user->id), $ticket->pool());
        $this->store()->forget($this->matchedKey($user->id));

        return $room instanceof GameRoom && $room->participantFor($user) !== null ? $room : $ticket;
    }

    public function leave(User $user): void
    {
        $pool = $this->poolOf($user->id);

        if ($pool !== null) {
            $this->withQueue($pool, function (array $queue) use ($user) {
                unset($queue[$user->id]);

                return [$queue, null];
            });
        }

        $this->store()->forget($this->userKey($user->id));
    }

    public function ticket(User $user): ?Ticket
    {
        $pool = $this->poolOf($user->id);

        return $pool !== null ? ($this->queue($pool)[$user->id] ?? null) : null;
    }

    /** How many players wait in the ticket's queue (same mode and group). */
    public function waiting(Ticket $ticket): int
    {
        return count($this->queue($ticket->pool()));
    }

    /** The room a recent match placed the player in (for clients that missed the event). */
    public function matchedRoom(User $user): ?string
    {
        $room = $this->store()->get($this->matchedKey($user->id));

        return is_string($room) ? $room : null;
    }

    public function heartbeat(User $user): ?Ticket
    {
        $pool = $this->poolOf($user->id);

        if ($pool === null) {
            return null;
        }

        $this->withQueue($pool, function (array $queue) use ($user) {
            if (isset($queue[$user->id])) {
                $queue[$user->id] = $queue[$user->id]->with(heartbeatAt: now()->getTimestamp());
            }

            return [$queue, null];
        });

        return $this->ticket($user);
    }

    /**
     * Drop abandoned tickets, form rooms of people, then fill tables with AI for those who waited long enough.
     * Run every few seconds by the scheduler.
     */
    public function sweep(): void
    {
        foreach ($this->store()->get('mm:modes', []) as $pool) {
            $pool = str_contains((string) $pool, ':') ? (string) $pool : self::pool((int) $pool, null);
            [$mode, $terms] = $this->poolTerms($pool) ?? [null, null];

            if ($mode === null) {
                continue;
            }

            $this->withQueue($pool, function (array $queue) use ($mode, $terms) {
                $now = now()->getTimestamp();

                foreach ($queue as $userId => $ticket) {
                    if ($ticket->heartbeatAt < $now - self::HEARTBEAT_TIMEOUT) {
                        unset($queue[$userId]);
                        $this->store()->forget($this->userKey($userId));
                    }
                }

                [$queue] = $this->match($mode, $queue, $terms);

                return [$this->fillWithAi($mode, $queue, $terms, $now), null];
            });
        }
    }

    /**
     * People first: once the oldest waiting player has waited the game's AI timeout and no table of people
     * formed, everyone waiting (oldest first, up to a table) sits down and AI fills the empty seats. The
     * room is an ordinary matchmaking room; nothing marks the AI players.
     *
     * @param  array<int, Ticket>  $queue
     * @return array<int, Ticket>
     */
    private function fillWithAi(GameMode $mode, array $queue, ?TableTerms $terms, int $now): array
    {
        uasort($queue, fn (Ticket $a, Ticket $b) => $a->joinedAt <=> $b->joinedAt);
        $size = $mode->max_players > 2 ? max($mode->min_players, min(self::BOT_TABLE_SIZE, $mode->max_players)) : $mode->min_players;

        while ($queue !== []) {
            $oldest = reset($queue);
            if ($oldest->botAt === null || $oldest->botAt > $now) {
                break;
            }
            $tickets = array_slice($queue, 0, $size, true);
            $players = User::query()->whereIn('id', array_keys($tickets))->get()
                ->filter(fn (User $u) => $this->stakes->canAfford($u, $mode, $terms))
                ->sortBy(fn (User $u) => $tickets[$u->id]->joinedAt)->values()->all();
            try {
                $ai = $this->ai->seats($mode->game, $size - count($players));
            } catch (InvalidArgumentException) {
                break;   // No AI plays the game now: they keep waiting for people.
            }
            foreach (array_keys($tickets) as $userId) {
                unset($queue[$userId]);
                $this->store()->forget($this->userKey($userId));
            }
            if ($players === []) {
                continue;
            }

            try {
                $room = $this->rooms->createStarted($mode, RoomType::Matchmaking, [...$players, ...$ai], terms: $terms);
            } catch (InsufficientFishkas) {
                continue;
            }
            $this->notifyMatched($room);
        }

        return $queue;
    }

    /**
     * Form as many rooms as the queue allows, oldest tickets first.
     *
     * @param  array<int, Ticket>  $queue
     * @return array{0: array<int, Ticket>, 1: GameRoom|null} Remaining queue and the last room formed.
     */
    private function match(GameMode $mode, array $queue, ?TableTerms $terms = null): array
    {
        $room = null;
        uasort($queue, fn (Ticket $a, Ticket $b) => $a->joinedAt <=> $b->joinedAt);
        $wait = $this->tableWait($mode);

        while (count($queue) >= $mode->min_players) {
            $oldest = reset($queue);
            $size = match (true) {
                count($queue) >= $mode->max_players => $mode->max_players,
                // Not full: start with whoever is here once the oldest has waited long enough.
                $wait === null || $oldest->joinedAt <= now()->getTimestamp() - $wait => count($queue),
                default => 0,
            };
            if ($size === 0) {
                break;
            }

            $tickets = array_slice($queue, 0, $size, true);
            // Someone whose Fishkas ran out while waiting (another game, an admin change) leaves the queue.
            $players = User::query()->whereIn('id', array_keys($tickets))->get()
                ->filter(fn (User $u) => $this->stakes->canAfford($u, $mode, $terms))
                ->sortBy(fn (User $u) => $tickets[$u->id]->joinedAt)->values()->all();

            foreach (array_keys($tickets) as $userId) {
                unset($queue[$userId]);
                $this->store()->forget($this->userKey($userId));
            }

            if (count($players) < $mode->min_players) {
                continue;
            }

            try {
                $room = $this->rooms->createStarted($mode, RoomType::Matchmaking, $players, terms: $terms);
            } catch (InsufficientFishkas) {
                continue;   // A balance changed in the meantime; nobody was charged.
            }
            $this->notifyMatched($room);
        }

        return [$queue, $room];
    }

    /** Seconds a table that isn't full waits for more players; null for fixed-size modes. */
    private function tableWait(GameMode $mode): ?int
    {
        if ($mode->max_players <= $mode->min_players) {
            return null;
        }

        return (int) ($mode->config['table_wait_seconds'] ?? self::TABLE_WAIT_SECONDS);
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
    private function withQueue(string $pool, Closure $change): mixed
    {
        $store = $this->store()->getStore();
        if (! $store instanceof LockProvider) {
            throw new LogicException('The game state store must support locks (use Redis).');
        }

        return $store->lock("mm-lock:{$pool}", 10)->block(5, function () use ($pool, $change) {
            [$queue, $result] = $change($this->queue($pool));

            $this->store()->forever("mm:queue:{$pool}", array_map(fn (Ticket $t) => $t->toArray(), $queue));
            $this->trackMode($pool, $queue !== []);

            return $result;
        });
    }

    /**
     * @return array<int, Ticket>
     */
    private function queue(string $pool): array
    {
        return array_map(fn (array $t) => Ticket::fromArray($t), $this->store()->get("mm:queue:{$pool}", []));
    }

    /** Keeps the list of queues with waiting players (what sweep() visits), under its own lock. */
    private function trackMode(string $pool, bool $hasTickets): void
    {
        /** @var LockProvider $store */
        $store = $this->store()->getStore();

        $store->lock('mm-lock:modes', 5)->block(5, function () use ($pool, $hasTickets) {
            $pools = array_map('strval', $this->store()->get('mm:modes', []));
            $pools = $hasTickets ? array_values(array_unique([...$pools, $pool])) : array_values(array_diff($pools, [$pool]));
            $this->store()->forever('mm:modes', $pools);
        });
    }

    /** The queue the player is in ("mode:group"; a bare mode id from before groups). */
    private function poolOf(int $userId): ?string
    {
        $pool = $this->store()->get($this->userKey($userId));

        return match (true) {
            is_string($pool) && str_contains($pool, ':') => $pool,
            is_numeric($pool) => self::pool((int) $pool, null),
            default => null,
        };
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
