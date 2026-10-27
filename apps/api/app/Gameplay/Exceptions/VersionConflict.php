<?php

namespace App\Gameplay\Exceptions;

use RuntimeException;

/** The client acted on an outdated state; it should refetch and try again. */
class VersionConflict extends RuntimeException
{
    public function __construct(public readonly int $currentVersion)
    {
        parent::__construct("The game has moved on (now at version {$currentVersion}).");
    }
}
