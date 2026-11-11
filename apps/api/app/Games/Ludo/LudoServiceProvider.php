<?php

namespace App\Games\Ludo;

use App\Gameplay\GameRegistry;
use Illuminate\Support\ServiceProvider;

class LudoServiceProvider extends ServiceProvider
{
    public function boot(GameRegistry $registry): void
    {
        $registry->register(new LudoDefinition);
    }
}
