<?php

namespace App\Platform\Presence;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * How many people are connected right now: the unique signed-in users on the "online" presence channel, which every
 * open page of a signed-in player joins (Reverb counts them; several tabs are one person). Asked of Reverb at most
 * every few seconds. Null when Reverb can't be asked: no number is better than a made-up one.
 */
class OnlinePlayers
{
    public const CHANNEL = 'presence-online';

    private const CACHE_SECONDS = 10;

    public function count(): ?int
    {
        /** @var int|null */
        return Cache::remember('online-players', self::CACHE_SECONDS, function (): ?int {
            try {
                /** @var object{user_count?: int} $info */
                $info = Broadcast::connection('reverb')->getPusher()->getChannelInfo(self::CHANNEL, ['info' => 'user_count']);

                return (int) ($info->user_count ?? 0);
            } catch (Throwable $e) {
                Log::warning('Online players could not be counted.', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }
}
