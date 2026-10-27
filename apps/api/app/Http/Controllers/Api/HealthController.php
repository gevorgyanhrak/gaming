<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

class HealthController extends Controller
{
    /**
     * Readiness probe.
     *
     * The API is "ready" when its critical dependencies (database, Redis) respond; the
     * status code depends only on those, so a load balancer keeps the API in rotation
     * while background services recover. Queue workers and the WebSocket server are
     * reported for monitoring but only mark the response as degraded.
     */
    public function ready(): JsonResponse
    {
        $critical = [
            'database' => $this->check('database', fn () => DB::select('select 1')),
            'redis' => $this->check('redis', fn () => Redis::connection()->ping()),
        ];

        $auxiliary = [
            'queue' => $this->check('queue', $this->horizonIsRunning(...)),
            'websocket' => $this->check('websocket', $this->reverbIsReachable(...)),
        ];

        $checks = [...$critical, ...$auxiliary];
        $ready = ! in_array('fail', $critical, true);

        return response()->json([
            'status' => match (true) {
                ! $ready => 'fail',
                in_array('fail', $auxiliary, true) => 'degraded',
                default => 'ok',
            },
            'checks' => $checks,
        ], $ready ? 200 : 503);
    }

    /**
     * @param  Closure(): mixed  $probe  Throws or returns false when the dependency is unhealthy.
     * @return 'ok'|'fail'
     */
    private function check(string $name, Closure $probe): string
    {
        try {
            if ($probe() !== false) {
                return 'ok';
            }

            Log::warning('Health check failed.', ['check' => $name]);
        } catch (Throwable $e) {
            Log::warning('Health check failed.', ['check' => $name, 'error' => $e->getMessage()]);
        }

        return 'fail';
    }

    private function horizonIsRunning(): bool
    {
        $masters = app(MasterSupervisorRepository::class)->all();

        return collect($masters)->contains(fn ($master) => $master->status === 'running');
    }

    private function reverbIsReachable(): bool
    {
        $socket = @fsockopen(
            (string) config('broadcasting.connections.reverb.options.host'),
            (int) config('broadcasting.connections.reverb.options.port'),
            timeout: 1.0,
        );

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
