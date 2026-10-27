<?php

namespace App\Games\Durak;

use App\Gameplay\GameRegistry;
use Illuminate\Support\ServiceProvider;

class DurakServiceProvider extends ServiceProvider
{
    public function boot(GameRegistry $registry): void
    {
        $registry->register(new DurakDefinition);
    }
}
