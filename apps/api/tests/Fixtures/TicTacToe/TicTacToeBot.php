<?php

namespace Tests\Fixtures\TicTacToe;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;

final class TicTacToeBot implements BotStrategy
{
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        return $legalActions[$random->int(0, count($legalActions) - 1)];
    }
}
