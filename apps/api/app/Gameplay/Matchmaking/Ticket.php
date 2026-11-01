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
        /** Unix time from which a bot game is offered or started; null when bots can't stand in. */
        public ?int $botAt,
        public bool $botOffered,
        public int $heartbeatAt,
        /** The play group (null for tickets from before groups). */
        public ?int $groupId = null,
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
            $data['botAt'] === null ? null : (int) $data['botAt'], (bool) $data['botOffered'], (int) $data['heartbeatAt'],
            isset($data['groupId']) ? (int) $data['groupId'] : null,
        );
    }

    public function with(?bool $botOffered = null, ?int $heartbeatAt = null): self
    {
        return new self($this->userId, $this->modeId, $this->joinedAt, $this->botAt, $botOffered ?? $this->botOffered, $heartbeatAt ?? $this->heartbeatAt, $this->groupId);
    }
}
