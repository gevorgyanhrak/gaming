<?php

namespace App\Gameplay\Rooms;

/**
 * The live state of an active room, as kept in the game state store.
 */
final readonly class RoomState
{
    /**
     * @param  int  $version  Sequence number of the last accepted move (0 = just started).
     * @param  array<string, mixed>  $state  Engine state.
     * @param  list<int>  $seatsToAct
     * @param  int|null  $deadline  Unix time by which the seats to act must move.
     */
    public function __construct(
        public int $version,
        public array $state,
        public array $seatsToAct,
        public ?int $deadline,
    ) {}

    /**
     * @return array{version: int, state: array<string, mixed>, seats_to_act: list<int>, deadline: int|null}
     */
    public function toArray(): array
    {
        return ['version' => $this->version, 'state' => $this->state, 'seats_to_act' => $this->seatsToAct, 'deadline' => $this->deadline];
    }

    /**
     * @param  array{version: int, state: array<string, mixed>, seats_to_act: list<int>, deadline: int|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['version'], $data['state'], $data['seats_to_act'], $data['deadline']);
    }
}
