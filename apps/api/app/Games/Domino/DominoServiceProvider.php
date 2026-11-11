<?php

namespace App\Games\Domino;

use App\Gameplay\GameRegistry;
use Illuminate\Support\ServiceProvider;

class DominoServiceProvider extends ServiceProvider
{
    public function boot(GameRegistry $registry): void
    {
        $registry->register(new DominoDefinition);
    }
}
