<?php

namespace App\Enums;

enum ParticipantResult: string
{
    case Win = 'win';
    case Loss = 'loss';
    case Draw = 'draw';
    /** Lost by timing out or leaving. */
    case Abandoned = 'abandoned';
}
