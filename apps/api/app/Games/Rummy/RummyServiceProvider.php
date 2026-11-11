<?php

namespace App\Games\Rummy;

use App\Gameplay\GameRegistry;
use Illuminate\Support\ServiceProvider;

class RummyServiceProvider extends ServiceProvider
{
    public function boot(GameRegistry $registry): void
    {
        $registry->register(new RummyDefinition);
    }
}
