<?php

namespace App\Console\Commands;

use App\Gameplay\Matchmaking\Matchmaker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:matchmaking-sweep')]
#[Description('Match waiting players, drop abandoned tickets and fall back to bots')]
class MatchmakingSweep extends Command
{
    public function handle(Matchmaker $matchmaker): int
    {
        $matchmaker->sweep();

        return self::SUCCESS;
    }
}
