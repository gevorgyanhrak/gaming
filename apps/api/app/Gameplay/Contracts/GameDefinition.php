<?php

namespace App\Gameplay\Contracts;

/**
 * Registers a game with the platform. One implementation per game, in app/Games/<Game>.
 */
interface GameDefinition
{
    /** Matches games.slug. */
    public function slug(): string;

    public function engine(): GameEngine;

    public function bot(): BotStrategy;

    /**
     * Bumped when rules change in a way that affects replays of older games.
     */
    public function version(): int;
}
