<?php

namespace App\Games\Pool;

use App\Gameplay\GameRegistry;
use Illuminate\Support\ServiceProvider;

class PoolServiceProvider extends ServiceProvider
{
    public function boot(GameRegistry $registry): void
    {
        $registry->register(new PoolDefinition);
    }
}
