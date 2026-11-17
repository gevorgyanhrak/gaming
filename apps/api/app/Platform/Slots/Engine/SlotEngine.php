<?php

namespace App\Platform\Slots\Engine;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The client of the internal slot engine (ADR 0026): the Slotopol games' maths behind a private API. It plays one
 * spin from a state and returns the next; it never sees players or Fishkas. Address and key come from config
 * (secrets, never admin settings).
 */
class SlotEngine
{
    /**
     * Every game the engine plays, as it describes them.
     *
     * @return list<array{id: string, name: string, provider: string, year?: int, width: int, height: int, symbols: int, lines: int, cost?: float, ways?: int, bonuses?: int, rtp: list<float>, measured?: float|null, verified?: bool, features: list<string>, state: array<string, mixed>}>
     *
     * @throws EngineUnavailable
     */
    public function games(): array
    {
        return $this->call(fn (PendingRequest $http) => $http->timeout(30)->get('/v1/games'))['data'];
    }

    /**
     * One spin. `bet` (per line) and `sel` (lines) of 0 keep the state's; the game refuses changes during free games.
     *
     * @param  array<string, mixed>  $state
     * @return array{state: array<string, mixed>, wins: list<array<string, mixed>>, cost: float, win: float, jack: float, free: bool, next: bool, fsr: int, total: float}
     *
     * @throws EngineUnavailable
     * @throws EngineRefused
     */
    public function spin(string $game, array $state, int $bet, int $sel, float $rtp): array
    {
        $body = ['game' => $game, 'rtp' => $rtp];
        if ($state !== []) {
            $body['state'] = $state;
        }
        if ($bet > 0) {
            $body['bet'] = $bet;
        }
        if ($sel > 0) {
            $body['sel'] = $sel;
        }

        $out = $this->call(fn (PendingRequest $http) => $http->post('/v1/spin', $body));

        // The answer moves Fishkas: anything not shaped as a spin, or a negative cost or win (Cash Farm's bonus can
        // pay below zero — audit 2026-11-18), is a fault, and the spin is refused with nothing charged.
        $number = fn (string $k) => isset($out[$k]) && (is_int($out[$k]) || is_float($out[$k])) && is_finite((float) $out[$k]) && $out[$k] >= 0;
        if (! is_array($out['state'] ?? null) || ! is_array($out['wins'] ?? null) || ! $number('cost') || ! $number('win')
            || ! $number('total') || ! is_bool($out['free'] ?? null) || ! is_bool($out['next'] ?? null) || ! is_int($out['fsr'] ?? null)) {
            throw new EngineUnavailable("slot engine gave an unusable answer for {$game}");
        }

        /** @var array{state: array<string, mixed>, wins: list<array<string, mixed>>, cost: float, win: float, jack: float, free: bool, next: bool, fsr: int, total: float} */
        return $out;
    }

    /**
     * @param  callable(PendingRequest): Response  $send
     * @return array<string, mixed>
     *
     * @throws EngineUnavailable
     * @throws EngineRefused
     */
    private function call(callable $send): array
    {
        $key = (string) config('services.slot_engine.key');
        if (strlen($key) < 32) {
            throw new EngineUnavailable('slot engine key is not set');
        }
        // The compose file's local default must never guard a production engine.
        if (app()->isProduction() && str_starts_with($key, 'local-dev-slot-engine-key')) {
            throw new EngineUnavailable('slot engine key is the local development default');
        }
        $http = Http::baseUrl((string) config('services.slot_engine.url'))
            ->withHeaders(['X-Engine-Key' => $key])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.slot_engine.timeout', 5));

        try {
            $response = $send($http);
        } catch (ConnectionException $e) {
            throw new EngineUnavailable($e->getMessage(), previous: $e);
        }
        if ($response->status() === 422) {
            throw new EngineRefused((string) $response->json('error', 'refused'));
        }
        if (! $response->successful() || ! is_array($response->json())) {
            throw new EngineUnavailable("slot engine answered {$response->status()}");
        }

        /** @var array<string, mixed> */
        return $response->json();
    }
}
