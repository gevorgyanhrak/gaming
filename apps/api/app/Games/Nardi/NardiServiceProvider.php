<?php

namespace App\Games\Nardi;

use App\Gameplay\GameRegistry;
use Illuminate\Support\ServiceProvider;

class NardiServiceProvider extends ServiceProvider
{
    public function boot(GameRegistry $registry): void
    {
        $registry->register(new NardiDefinition);
    }
}
