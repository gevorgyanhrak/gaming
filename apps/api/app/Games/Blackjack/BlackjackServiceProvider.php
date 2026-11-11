<?php

namespace App\Games\Blackjack;

use App\Gameplay\GameRegistry;
use Illuminate\Support\ServiceProvider;

class BlackjackServiceProvider extends ServiceProvider
{
    public function boot(GameRegistry $registry): void
    {
        $registry->register(new BlackjackDefinition);
    }
}
