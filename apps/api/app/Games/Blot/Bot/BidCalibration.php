<?php

namespace App\Games\Blot\Bot;

/**
 * What self-play taught the AI about its own bidding (docs/games/blot-ai.md, "Learning from self-play"): for
 * each band of the raw chance the look-ahead gives a contract (share of sampled deals that make it), how
 * often contracts it bid with that chance were really made. BidPlanner reads its chance through this table.
 * Built by `php artisan blot:calibrate` from the notes `blot:arena --record` writes; kept in
 * bid-calibration.json next to this class. Each band is pulled towards its own raw chance by PRIOR deals,
 * so a thin band changes little.
 */
final class BidCalibration
{
    public const FILE = __DIR__.'/bid-calibration.json';

    public const BANDS = 10;

    /** How many deals' worth of trust the raw chance keeps in each band. */
    public const PRIOR = 10;

    private static ?self $loaded = null;

    /**
     * @param  list<array{n: int, made: int, chance: float}>  $bands  per band: contracts, made, sum of raw chances
     */
    public function __construct(private readonly array $bands) {}

    /** The table in bid-calibration.json, or null when there is none yet. */
    public static function load(): ?self
    {
        if (self::$loaded === null && is_file(self::FILE)) {
            $data = json_decode((string) file_get_contents(self::FILE), true);
            if (is_array($data) && isset($data['bands']) && is_array($data['bands']) && count($data['bands']) === self::BANDS) {
                self::$loaded = new self(array_values($data['bands']));
            }
        }

        return self::$loaded;
    }

    /**
     * Builds the bands from self-play notes.
     *
     * @param  iterable<array{chance: float|int, made: bool}>  $notes
     */
    public static function learn(iterable $notes): self
    {
        $bands = array_fill(0, self::BANDS, ['n' => 0, 'made' => 0, 'chance' => 0.0]);
        foreach ($notes as $note) {
            $band = self::band((float) $note['chance']);
            $bands[$band]['n']++;
            $bands[$band]['made'] += $note['made'] ? 1 : 0;
            $bands[$band]['chance'] += (float) $note['chance'];
        }

        return new self($bands);
    }

    /** How often a contract the look-ahead makes in $chance of its deals is really made. */
    public function chance(float $chance): float
    {
        $band = $this->bands[self::band($chance)];

        return ($band['made'] + self::PRIOR * $chance) / ($band['n'] + self::PRIOR);
    }

    /**
     * @return list<array{n: int, made: int, chance: float}>
     */
    public function bands(): array
    {
        return $this->bands;
    }

    private static function band(float $chance): int
    {
        return min(self::BANDS - 1, max(0, (int) floor($chance * self::BANDS)));
    }
}
