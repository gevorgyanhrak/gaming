<?php

namespace App\Gameplay\Exceptions;

use RuntimeException;

/** The action is not legal in the current state (or not this seat's turn). */
class IllegalAction extends RuntimeException {}
