<?php

namespace App\Console\Commands;

use App\Gameplay\Lifecycle\RoomPresence;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:abandonment-sweep')]
#[Description('End games for players who left their game page')]
class AbandonmentSweep extends Command
{
    public function handle(RoomPresence $presence): int
    {
        $presence->sweep();

        return self::SUCCESS;
    }
}
