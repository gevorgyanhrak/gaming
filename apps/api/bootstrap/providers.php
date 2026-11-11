<?php

use App\Games\Blackjack\BlackjackServiceProvider;
use App\Games\Blot\BlotServiceProvider;
use App\Games\Checkers\CheckersServiceProvider;
use App\Games\Chess\ChessServiceProvider;
use App\Games\Domino\DominoServiceProvider;
use App\Games\Durak\DurakServiceProvider;
use App\Games\Ludo\LudoServiceProvider;
use App\Games\Nardi\NardiServiceProvider;
use App\Games\Poker\PokerServiceProvider;
use App\Games\Pool\PoolServiceProvider;
use App\Games\Rummy\RummyServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    // Games register their engines with the GameRegistry.
    NardiServiceProvider::class,
    CheckersServiceProvider::class,
    ChessServiceProvider::class,
    DurakServiceProvider::class,
    PokerServiceProvider::class,
    BlackjackServiceProvider::class,
    BlotServiceProvider::class,
    DominoServiceProvider::class,
    LudoServiceProvider::class,
    PoolServiceProvider::class,
    RummyServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
];
