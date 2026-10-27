<?php

namespace App\Games\Poker;

use App\Gameplay\GameRegistry;
use Illuminate\Support\ServiceProvider;

class PokerServiceProvider extends ServiceProvider
{
    public function boot(GameRegistry $registry): void
    {
        $registry->register(new PokerDefinition);
    }
}
