<?php

namespace App\Gameplay\Engine;

/**
 * What GameActionService::submit returns: the room's new version and the acting seat's view.
 */
final readonly class ActionResult
{
    /**
     * @param  bool  $replayed  The same client_action_id was already accepted; nothing new happened.
     */
    public function __construct(
        public int $version,
        public bool $replayed = false,
    ) {}
}
