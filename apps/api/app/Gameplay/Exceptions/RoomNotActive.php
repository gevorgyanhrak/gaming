<?php

namespace App\Gameplay\Exceptions;

use RuntimeException;

/** The room has not started yet or is already over. */
class RoomNotActive extends RuntimeException {}
