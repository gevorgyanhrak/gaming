<?php

namespace App\Platform\Moderation;

use RuntimeException;

/** A moderation action that isn't allowed; the message is user-facing. */
class ModerationException extends RuntimeException {}
