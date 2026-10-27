<?php

namespace App\Games\Checkers;

use App\Gameplay\GameRegistry;
use Illuminate\Support\ServiceProvider;

class CheckersServiceProvider extends ServiceProvider
{
    public function boot(GameRegistry $registry): void
    {
        $registry->register(new CheckersDefinition);
    }
}
