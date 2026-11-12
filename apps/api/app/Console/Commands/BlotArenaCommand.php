<?php

namespace App\Console\Commands;

use App\Enums\BotDifficulty;
use App\Gameplay\Random\RandomSource;
use App\Games\Blot\Arena\BlotArena;
use App\Games\Blot\Bot\BlotBot;
use App\Games\Blot\Bot\ClassicBlotBot;
use Illuminate\Console\Command;

/**
 * Blot AI against the rule-based baseline on duplicate deals (development tool; see BlotArena).
 */
class BlotArenaCommand extends Command
{
    protected $signature = 'blot:arena {deals=100 : duplicate deals (each played twice)} {--seed=1} {--a=pro : pro|pro0|classic} {--b=classic : pro|pro0|classic} {--level=hard : easy|medium|hard}';

    protected $description = 'Play blot strategies against each other on duplicate deals and report the results';

    public function handle(BlotArena $arena): int
    {
        // pro: the AI as it plays; pro0: without the research techniques (exact endgame, merged cards); classic: the old bot.
        $pick = fn (string $name) => match ($name) {
            'classic' => new ClassicBlotBot,
            'pro0' => new BlotBot(research: false),
            default => new BlotBot,
        };
        $deals = (int) $this->argument('deals');
        $bar = $this->output->createProgressBar($deals);
        $stats = $arena->run(
            $pick((string) $this->option('a')),
            $pick((string) $this->option('b')),
            $deals,
            (int) $this->option('seed'),
            fn (int $seed): RandomSource => new class($seed) implements RandomSource
            {
                private int $state;

                public function __construct(int $seed)
                {
                    $this->state = ($seed * 2654435761) & 0xFFFFFFFF ?: 1;
                }

                public function int(int $min, int $max): int
                {
                    $x = $this->state;
                    $x ^= ($x << 13) & 0xFFFFFFFF;
                    $x ^= $x >> 17;
                    $x ^= ($x << 5) & 0xFFFFFFFF;
                    $this->state = $x & 0xFFFFFFFF;

                    return $min + $this->state % ($max - $min + 1);
                }
            },
            BotDifficulty::from((string) $this->option('level')),
            fn (int $done) => $bar->setProgress($done),
        );
        $bar->finish();
        $this->newLine(2);
        $rows = [];
        foreach (array_keys($stats['a']) as $key) {
            $rows[] = [$key, $stats['a'][$key], $stats['b'][$key]];
        }
        $this->table(['', 'A: '.$this->option('a'), 'B: '.$this->option('b')], $rows);

        return self::SUCCESS;
    }
}
