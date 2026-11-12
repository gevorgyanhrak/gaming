<?php

namespace App\Gameplay\Matchmaking;

use App\Enums\BotDifficulty;
use App\Enums\BotFallbackMode;
use App\Enums\PlayGroupKind;
use App\Enums\RoomType;
use App\Gameplay\Bots\AiPlayers;
use App\Gameplay\Rooms\PlayGroups;
use App\Gameplay\Rooms\PlayRules;
use App\Gameplay\Rooms\RoomService;
use App\Gameplay\Rooms\RoomStakes;
use App\Gameplay\Rooms\TableTerms;
use App\Models\BotProfile;
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
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Pairs waiting players per game mode; people first, then AI fills the empty seats — for every game.
 *
 * The countdown (owner, 2026-11-12): a search shows a countdown to `matchmaking.start_seconds` (10). People
 * are matched as they arrive; a full table of people starts at once. When the oldest waiting player has
 * waited the AI time (`matchmaking.default_bot_timeout_seconds`, 7, or the game's own), a table is reserved
 * for everyone waiting and AI takes the empty seats (reserveTables); everyone sees it form, and a person who
 * arrives before it starts takes an AI seat (people first). It starts at its time (startTables). Delayed
 * MatchmakingTick jobs keep those moments on time; the 5-second sweep is the safety net.
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
            // A table AI is filling has room for a person: they take a seat there (people first).
            $queue[$ticket->userId] = $this->joinTable($ticket, $queue, $mode);
            if ($queue[$ticket->userId]->table !== null) {
                return [$queue, null];
            }

            return $this->match($mode, $queue, $terms);
        });
        $this->store()->forever($this->userKey($user->id), $ticket->pool());
        $this->store()->forget($this->matchedKey($user->id));
        if ($ticket->botAt !== null) {
            MatchmakingTick::dispatch($ticket->pool())->onQueue(config('gameplay.queue'))->delay(now()->setTimestamp($ticket->botAt));
        }

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
     * Every queue: drop abandoned tickets, match people, reserve tables for AI to fill, start tables whose
     * time has come. Run every few seconds by the scheduler (MatchmakingTick does one queue on time).
     */
    public function sweep(): void
    {
        foreach ($this->store()->get('mm:modes', []) as $pool) {
            $this->step(str_contains((string) $pool, ':') ? (string) $pool : self::pool((int) $pool, null));
        }
    }

    /** One queue's moment: idempotent, safe to run at any time. */
    public function step(string $pool): void
    {
        [$mode, $terms] = $this->poolTerms($pool) ?? [null, null];
        if ($mode === null) {
            return;
        }

        $this->withQueue($pool, function (array $queue) use ($mode, $terms, $pool) {
            $now = now()->getTimestamp();
            foreach ($queue as $userId => $ticket) {
                if ($ticket->heartbeatAt < $now - self::HEARTBEAT_TIMEOUT) {
                    unset($queue[$userId]);
                    $this->store()->forget($this->userKey($userId));
                }
            }

            [$queue] = $this->match($mode, $queue, $terms);
            $queue = $this->reserveTables($pool, $mode, $queue, $terms, $now);

            return [$this->startTables($pool, $mode, $queue, $terms, $now), null];
        });
    }

    /** Seats at a table AI fills: two-player modes their two, bigger ones BOT_TABLE_SIZE within their range. */
    private function tableSize(GameMode $mode): int
    {
        return $mode->max_players > 2 ? max($mode->min_players, min(self::BOT_TABLE_SIZE, $mode->max_players)) : $mode->min_players;
    }

    /**
     * Once the oldest player not yet at a table has waited the AI time, reserve a table for the oldest
     * waiting (up to a table) with AI in the other seats; it starts at the oldest's countdown end.
     *
     * @param  array<int, Ticket>  $queue
     * @return array<int, Ticket>
     */
    private function reserveTables(string $pool, GameMode $mode, array $queue, ?TableTerms $terms, int $now): array
    {
        $size = $this->tableSize($mode);
        while (true) {
            $free = array_filter($queue, fn (Ticket $t) => $t->table === null);
            uasort($free, fn (Ticket $a, Ticket $b) => $a->joinedAt <=> $b->joinedAt);
            $oldest = reset($free);
            if ($oldest === false || $oldest->botAt === null || $oldest->botAt > $now) {
                return $queue;
            }
            $tickets = array_slice($free, 0, $size, true);
            $people = User::query()->whereIn('id', array_keys($tickets))->get()
                ->filter(fn (User $u) => $this->stakes->canAfford($u, $mode, $terms))->pluck('id')->all();
            foreach (array_keys($tickets) as $userId) {
                if (! in_array($userId, $people, true)) {
                    unset($queue[$userId]);
                    $this->store()->forget($this->userKey($userId));
                }
            }
            if ($people === []) {
                continue;
            }
            try {
                $ai = $this->ai->seats($mode->game, $size - count($people));
            } catch (InvalidArgumentException) {
                return $queue;   // No AI plays the game now: they keep waiting for people.
            }

            $id = Str::ulid()->toBase32();
            $startAt = max($now, $oldest->joinedAt + $this->startSeconds());
            $this->store()->put("mm:table:{$id}", [
                'pool' => $pool,
                'start_at' => $startAt,
                'size' => $size,
                'bots' => array_map(fn (array $seat) => ['id' => $seat['bot']->id, 'difficulty' => $seat['difficulty']->value], $ai),
            ], 600);
            $this->store()->forever("mm:tables:{$pool}", [...$this->tableIds($pool), $id]);
            foreach ($people as $userId) {
                $queue[$userId] = $queue[$userId]->with(table: $id);
                $this->notify($userId, 'table', ['starts_at' => now()->setTimestamp($startAt)->toIso8601String()]);
            }
            MatchmakingTick::dispatch($pool)->onQueue(config('gameplay.queue'))->delay(now()->setTimestamp($startAt));
        }
    }

    /**
     * Start every reserved table whose time has come, with the people still at it and AI in every other seat.
     *
     * @param  array<int, Ticket>  $queue
     * @return array<int, Ticket>
     */
    private function startTables(string $pool, GameMode $mode, array $queue, ?TableTerms $terms, int $now): array
    {
        $kept = [];
        foreach ($this->tableIds($pool) as $id) {
            $table = $this->store()->get("mm:table:{$id}");
            $tickets = array_filter($queue, fn (Ticket $t) => $t->table === $id);
            if (! is_array($table) || $tickets === []) {
                $this->store()->forget("mm:table:{$id}");

                continue;
            }
            if ($table['start_at'] > $now) {
                $kept[] = $id;

                continue;
            }
            foreach (array_keys($tickets) as $userId) {
                unset($queue[$userId]);
                $this->store()->forget($this->userKey($userId));
            }
            $this->store()->forget("mm:table:{$id}");

            $people = User::query()->whereIn('id', array_keys($tickets))->get()
                ->filter(fn (User $u) => $this->stakes->canAfford($u, $mode, $terms))
                ->sortBy(fn (User $u) => $tickets[$u->id]->joinedAt)->values()->all();
            if ($people === []) {
                continue;
            }
            $bots = BotProfile::query()->whereIn('id', array_column($table['bots'], 'id'))->get()->keyBy('id');
            $ai = [];
            foreach ($table['bots'] as $seat) {
                $bot = $bots->get($seat['id']);
                if ($bot !== null) {
                    $ai[] = ['bot' => $bot, 'difficulty' => BotDifficulty::from($seat['difficulty'])];
                }
            }
            try {
                // Seats a person left before the start go to AI too.
                $missing = $table['size'] - count($people) - count($ai);
                $ai = [...array_slice($ai, 0, max(0, $table['size'] - count($people))), ...($missing > 0 ? $this->ai->seats($mode->game, $missing) : [])];
                $room = $this->rooms->createStarted($mode, RoomType::Matchmaking, [...$people, ...$ai], terms: $terms);
            } catch (InsufficientFishkas|InvalidArgumentException) {
                continue;
            }
            $this->notifyMatched($room);
        }
        $this->store()->forever("mm:tables:{$pool}", $kept);

        return $queue;
    }

    /**
     * A person arriving while a table is being filled takes a free or AI seat there.
     *
     * @param  array<int, Ticket>  $queue
     */
    private function joinTable(Ticket $ticket, array $queue, GameMode $mode): Ticket
    {
        if ($ticket->botAt === null) {
            return $ticket;
        }
        foreach ($this->tableIds($ticket->pool()) as $id) {
            $table = $this->store()->get("mm:table:{$id}");
            if (! is_array($table) || $table['start_at'] <= now()->getTimestamp()) {
                continue;
            }
            $people = count(array_filter($queue, fn (Ticket $t) => $t->table === $id && $t->userId !== $ticket->userId));
            if ($people + count($table['bots']) >= $table['size']) {
                if ($table['bots'] === []) {
                    continue;
                }
                array_pop($table['bots']);   // a person instead of an AI player
                $this->store()->put("mm:table:{$id}", $table, 600);
            }

            return $ticket->with(table: $id);
        }

        return $ticket;
    }

    /**
     * The players at the ticket's reserved table, for the countdown screen: people and AI alike.
     *
     * @return list<array{name: string, avatar_url: string|null, level: int}>|null
     */
    public function tablePlayers(Ticket $ticket): ?array
    {
        $table = $ticket->table === null ? null : $this->store()->get("mm:table:{$ticket->table}");
        if (! is_array($table)) {
            return null;
        }
        $people = array_keys(array_filter($this->queue($ticket->pool()), fn (Ticket $t) => $t->table === $ticket->table));
        $players = [];
        foreach (User::query()->with('profile')->whereIn('id', $people)->get() as $user) {
            $players[] = ['name' => $user->profile->display_name ?? $user->username, 'avatar_url' => $user->profile->avatarUrl(), 'level' => (int) $user->level];
        }
        foreach (BotProfile::query()->whereIn('id', array_column($table['bots'], 'id'))->get() as $bot) {
            $players[] = ['name' => $bot->name, 'avatar_url' => $bot->avatarUrl(), 'level' => (int) $bot->level];
        }

        return $players;
    }

    /** When the ticket's game starts (its table's time, or the countdown end while still searching). */
    public function startsAt(Ticket $ticket): ?int
    {
        $table = $ticket->table === null ? null : $this->store()->get("mm:table:{$ticket->table}");
        if (is_array($table)) {
            return (int) $table['start_at'];
        }

        return $ticket->botAt === null ? null : $ticket->joinedAt + $this->startSeconds();
    }

    private function startSeconds(): int
    {
        return (int) $this->settings->get('matchmaking.start_seconds');
    }

    /**
     * @return list<string>
     */
    private function tableIds(string $pool): array
    {
        return array_values(array_map('strval', $this->store()->get("mm:tables:{$pool}", [])));
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
        // Players already at a table AI is filling start with it.
        $seated = array_filter($queue, fn (Ticket $t) => $t->table !== null);
        $queue = array_filter($queue, fn (Ticket $t) => $t->table === null);
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

        return [$queue + $seated, $room];
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
