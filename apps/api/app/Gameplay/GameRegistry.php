<?php

namespace App\Gameplay;

use App\Gameplay\Contracts\GameDefinition;
use InvalidArgumentException;

/**
 * The games whose engines exist. A game can only be played (games.is_enabled) when registered here.
 */
class GameRegistry
{
    /** @var array<string, GameDefinition> */
    private array $definitions = [];

    public function register(GameDefinition $definition): void
    {
        $this->definitions[$definition->slug()] = $definition;
    }

    public function has(string $slug): bool
    {
        return isset($this->definitions[$slug]);
    }

    public function get(string $slug): GameDefinition
    {
        return $this->definitions[$slug] ?? throw new InvalidArgumentException("No engine is registered for game [{$slug}].");
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_keys($this->definitions);
    }
}
