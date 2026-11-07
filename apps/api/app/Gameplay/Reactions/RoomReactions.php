<?php

namespace App\Gameplay\Reactions;

use App\Enums\RoomStatus;
use App\Gameplay\Broadcasting\RoomReactionSent;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Platform\Levels\Levels;
use App\Platform\Settings\Settings;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Emojis and predefined messages between the people at a table (ADR 0017). They are not stored in the
 * database: each goes out at once on every person's private channel (`room.reaction`, like room
 * updates), and the room keeps its last ones for a few hours in the game store: the table chat's history
 * after a reload, and catch-up for a page without a live connection (polling) or one that reconnected. A repeated request (same client id) sends nothing twice.
 *
 * Who may send: a person seated at the room, while it waits for players, while it plays, and for a
 * while after it ends ("GG!"). To one other seat, or to everyone.
 */
class RoomReactions
{
    /** How long the room remembers a reaction: the table chat's history, catch-up after a reconnect. */
    public const KEEP_SECONDS = 3 * 3600;

    /** How long after the end of a game its players can still react. */
    public const OPEN_AFTER_END_MINUTES = 10;

    private const KEEP = 50;

    public function __construct(
        private readonly ReactionCatalog $catalog,
        private readonly Settings $settings,
        private readonly Levels $levels,
    ) {}

    /**
     * @return array{id: string, seq: int, room: string, from_seat: int, to_seat: int|null, kind: string, value: string, sent_at: string}
     *
     * @throws ValidationException|HttpException
     */
    public function send(GameRoom $room, GameParticipant $from, string $kind, string $value, ?int $toSeat, string $clientId): array
    {
        if (! $this->settings->get('reactions.enabled')) {
            throw new HttpException(403, __('platform.reactions.disabled'));
        }
        if (! $this->open($room)) {
            throw new HttpException(409, __('platform.reactions.closed'));
        }
        // Exclusive emojis (ADR 0018) belong to players who reached the level that unlocks them.
        $unlocksAt = $kind === 'emoji' ? ($this->levels->emojiLevels()[$value] ?? null) : null;
        if ($unlocksAt === null && ! $this->catalog->has($kind, $value)) {
            throw ValidationException::withMessages(['value' => __('platform.reactions.unknown')]);
        }
        if ($unlocksAt !== null && $from->user->level < $unlocksAt) {
            throw ValidationException::withMessages(['value' => __('platform.reactions.locked', ['level' => $unlocksAt])]);
        }
        if ($toSeat !== null && ($toSeat === $from->seat || ! $room->participants->contains('seat', $toSeat))) {
            throw ValidationException::withMessages(['to_seat' => __('platform.reactions.no_such_player')]);
        }

        $sentKey = "room-reaction:{$room->id}:{$from->id}:{$clientId}";
        [$reaction, $new] = $this->locked($room, function () use ($room, $from, $kind, $value, $toSeat, $sentKey) {
            /** @var array{id: string, seq: int, room: string, from_seat: int, to_seat: int|null, kind: string, value: string, sent_at: string}|null $sent */
            $sent = $this->store()->get($sentKey);
            if ($sent !== null) {
                return [$sent, false];
            }

            $recent = $this->recent($room);
            $reaction = [
                'id' => (string) Str::ulid(),
                'seq' => ($recent === [] ? (int) $this->store()->get($this->seqKey($room), 0) : end($recent)['seq']) + 1,
                'room' => $room->public_id,
                'from_seat' => $from->seat,
                'to_seat' => $toSeat,
                'kind' => $kind,
                'value' => $value,
                'sent_at' => now()->toIso8601String(),
            ];
            $this->store()->put($this->listKey($room), array_slice([...$recent, $reaction], -self::KEEP), self::KEEP_SECONDS);
            // The sequence outlives the list, so numbers never repeat in a room.
            $this->store()->put($this->seqKey($room), $reaction['seq'], now()->addDay());
            $this->store()->put($sentKey, $reaction, self::KEEP_SECONDS);

            return [$reaction, true];
        });

        if ($new) {
            $this->broadcast($room, $reaction);
        }

        return $reaction;
    }

    /**
     * The room's reactions after sequence number $after (all it remembers when null), oldest first.
     *
     * @return list<array{id: string, seq: int, room: string, from_seat: int, to_seat: int|null, kind: string, value: string, sent_at: string}>
     */
    public function since(GameRoom $room, ?int $after): array
    {
        return array_values(array_filter($this->recent($room), fn (array $r) => $after === null || $r['seq'] > $after));
    }

    /** Whether the room's players can react now. */
    public function open(GameRoom $room): bool
    {
        return match ($room->status) {
            RoomStatus::Waiting, RoomStatus::Active => true,
            RoomStatus::Finished => $room->finished_at !== null && $room->finished_at->gt(now()->subMinutes(self::OPEN_AFTER_END_MINUTES)),
            default => false,
        };
    }

    /**
     * @param  array{id: string, seq: int, room: string, from_seat: int, to_seat: int|null, kind: string, value: string, sent_at: string}  $reaction
     */
    private function broadcast(GameRoom $room, array $reaction): void
    {
        foreach ($room->participants as $participant) {
            if ($participant->user_id === null) {
                continue;
            }
            try {
                event(new RoomReactionSent($participant->user_id, $reaction));
            } catch (Throwable $e) {
                Log::warning('Reaction broadcast failed.', ['room' => $room->public_id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * The remembered reactions, without those older than KEEP_SECONDS.
     *
     * @return list<array{id: string, seq: int, room: string, from_seat: int, to_seat: int|null, kind: string, value: string, sent_at: string}>
     */
    private function recent(GameRoom $room): array
    {
        $cutoff = now()->subSeconds(self::KEEP_SECONDS);
        /** @var list<array{id: string, seq: int, room: string, from_seat: int, to_seat: int|null, kind: string, value: string, sent_at: string}> $list */
        $list = $this->store()->get($this->listKey($room), []);

        return array_values(array_filter($list, fn (array $r) => now()->parse($r['sent_at'])->gt($cutoff)));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function locked(GameRoom $room, callable $callback): mixed
    {
        $store = $this->store()->getStore();
        if (! $store instanceof LockProvider) {
            throw new LogicException('The game state store must support locks (use Redis).');
        }

        return $store->lock("room-reactions-lock:{$room->id}", 5)->block(3, $callback);
    }

    private function listKey(GameRoom $room): string
    {
        return "room-reactions:{$room->id}";
    }

    private function seqKey(GameRoom $room): string
    {
        return "room-reactions-seq:{$room->id}";
    }

    private function store(): Repository
    {
        return Cache::store(config('gameplay.state_store'));
    }
}
