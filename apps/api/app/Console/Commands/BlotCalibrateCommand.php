<?php

namespace App\Console\Commands;

use App\Games\Blot\Bot\BidCalibration;
use Illuminate\Console\Command;

/**
 * Learns the blot AI's bid calibration from self-play notes (`blot:arena --a=raw --b=raw --record=…`) and
 * writes it to BidCalibration::FILE (development tool; docs/games/blot-ai.md, "Learning from self-play").
 */
class BlotCalibrateCommand extends Command
{
    protected $signature = 'blot:calibrate {files* : self-play note files (JSON lines)} {--dry : show the table without writing it}';

    protected $description = 'Learn how often the blot AI\'s bids are really made, from self-play notes';

    public function handle(): int
    {
        $notes = [];
        foreach ((array) $this->argument('files') as $file) {
            foreach (file((string) $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $note = json_decode($line, true);
                // Kaput is priced by its own rule (every deal must sweep), not by this table.
                if (is_array($note) && ! ($note['kaput'] ?? false)) {
                    $notes[] = $note;
                }
            }
        }
        $table = BidCalibration::learn($notes);

        $rows = [];
        foreach ($table->bands() as $i => $band) {
            $raw = $band['n'] > 0 ? $band['chance'] / $band['n'] : null;
            $rows[] = [
                sprintf('%d–%d%%', $i * 10, $i * 10 + 10),
                $band['n'],
                $raw === null ? '—' : sprintf('%.0f%%', $raw * 100),
                $band['n'] > 0 ? sprintf('%.0f%%', 100 * $band['made'] / $band['n']) : '—',
                $raw === null ? '—' : sprintf('%.0f%%', 100 * $table->chance($raw)),
            ];
        }
        $this->table(['AI\'s chance', 'contracts', 'it said (avg)', 'really made', 'it will now use'], $rows);
        $this->line(count($notes).' contracts learned from.');

        if (! $this->option('dry')) {
            file_put_contents(BidCalibration::FILE, json_encode([
                'learned_from' => count($notes),
                'learned_at' => now()->toDateString(),
                'bands' => $table->bands(),
            ], JSON_PRETTY_PRINT)."\n");
            $this->info('Written to '.BidCalibration::FILE);
        }

        return self::SUCCESS;
    }
}
