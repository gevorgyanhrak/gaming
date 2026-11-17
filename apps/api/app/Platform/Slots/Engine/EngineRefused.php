<?php

namespace App\Platform\Slots\Engine;

use RuntimeException;

/** The game's own rules refused the request (a bet or lines change during free games, lines out of its set). */
final class EngineRefused extends RuntimeException {}
