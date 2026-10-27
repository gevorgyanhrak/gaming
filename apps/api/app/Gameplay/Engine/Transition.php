<?php

namespace App\Gameplay\Engine;

/**
 * The result of applying an action: the new state, plus events the UI may animate
 * (e.g. dice rolled, checker hit). Events must not contain hidden information.
 */
final readonly class Transition
{
    /**
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>  $events
     */
    public function __construct(
        public array $state,
        public array $events = [],
    ) {}
}
