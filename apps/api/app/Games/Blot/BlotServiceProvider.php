<?php

namespace App\Games\Blot;

use App\Gameplay\GameRegistry;
use Illuminate\Support\ServiceProvider;

class BlotServiceProvider extends ServiceProvider
{
    public function boot(GameRegistry $registry): void
    {
        $registry->register(new BlotDefinition);
    }
}
