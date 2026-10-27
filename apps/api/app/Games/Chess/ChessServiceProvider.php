<?php

namespace App\Games\Chess;

use App\Gameplay\GameRegistry;
use Illuminate\Support\ServiceProvider;

class ChessServiceProvider extends ServiceProvider
{
    public function boot(GameRegistry $registry): void
    {
        $registry->register(new ChessDefinition);
    }
}
