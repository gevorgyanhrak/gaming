<?php

use App\Games\Checkers\CheckersServiceProvider;
use App\Games\Chess\ChessServiceProvider;
use App\Games\Nardi\NardiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    // Games register their engines with the GameRegistry.
    NardiServiceProvider::class,
    CheckersServiceProvider::class,
    ChessServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
];
