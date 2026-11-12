<?php

namespace App\Gameplay\Matchmaking;

/**
 * A player waiting for an opponent in one game mode and play group.
 */
final readonly class Ticket
{
    public function __construct(
        public int $userId,
        public int $modeId,
        public int $joinedAt,
        /** Unix time from which AI may fill the table's empty seats; null when AI can't play this game. */
        public ?int $botAt,
        public int $heartbeatAt,
        /** The play group (null for tickets from before groups). */
        public ?int $groupId = null,
        /** The table reserved for this player (AI fills its other seats; it starts at its time), or null. */
        public ?string $table = null,
    ) {}

    /** The queue this ticket waits in: one per mode and group. */
    public function pool(): string
    {
        return Matchmaker::pool($this->modeId, $this->groupId);
    }

    /**
     * @return array<string, int|bool|null>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['userId'], (int) $data['modeId'], (int) $data['joinedAt'],
            $data['botAt'] === null ? null : (int) $data['botAt'], (int) $data['heartbeatAt'],
            isset($data['groupId']) ? (int) $data['groupId'] : null,
            isset($data['table']) ? (string) $data['table'] : null,
        );
    }

    public function with(?int $heartbeatAt = null, ?string $table = null): self
    {
        return new self($this->userId, $this->modeId, $this->joinedAt, $this->botAt, $heartbeatAt ?? $this->heartbeatAt, $this->groupId, $table ?? $this->table);
    }
}
