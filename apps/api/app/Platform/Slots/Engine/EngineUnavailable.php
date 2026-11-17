<?php

namespace App\Platform\Slots\Engine;

use RuntimeException;

/** The slot engine can't be reached or failed: nothing was charged. */
final class EngineUnavailable extends RuntimeException {}
