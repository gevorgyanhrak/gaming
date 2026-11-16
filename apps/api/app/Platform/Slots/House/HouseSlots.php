<?php

namespace App\Platform\Slots\House;

/** The house slot games the code has (container tag "house-slots"), by id. */
final class HouseSlots
{
    /** The provider row these games belong to. */
    public const PROVIDER = 'apexslots';

    /** @var array<string, HouseSlot> */
    private array $byId = [];

    /**
     * @param  iterable<HouseSlot>  $games
     */
    public function __construct(iterable $games)
    {
        foreach ($games as $game) {
            $this->byId[$game->id()] = $game;
        }
    }

    public function for(string $id): ?HouseSlot
    {
        return $this->byId[$id] ?? null;
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->byId);
    }
}
