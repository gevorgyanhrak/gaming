<?php

namespace App\Console\Commands;

use App\Platform\Slots\Engine\EngineSlots;
use App\Platform\Slots\Engine\EngineUnavailable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('slots:engine-sync')]
#[Description('Add the slot engine\'s games to the catalogue and refresh their descriptions (admin changes kept; safe to repeat)')]
class SyncEngineSlots extends Command
{
    public function handle(EngineSlots $slots): int
    {
        try {
            $result = $slots->sync();
        } catch (EngineUnavailable $e) {
            $this->error("The slot engine is not reachable: {$e->getMessage()}");

            return self::FAILURE;
        }
        $this->info("{$result['total']} engine game(s): {$result['added']} added, {$result['updated']} refreshed.");

        return self::SUCCESS;
    }
}
