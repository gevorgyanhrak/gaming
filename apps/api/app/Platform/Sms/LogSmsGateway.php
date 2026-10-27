<?php

namespace App\Platform\Sms;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Development driver: writes messages to the application log instead of sending them.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): void
    {
        // Messages contain one-time codes; they must never end up in production logs.
        if (app()->isProduction()) {
            throw new RuntimeException('The "log" SMS driver cannot be used in production. Configure a real SMS provider.');
        }

        Log::info('SMS (log driver)', ['to' => $to, 'message' => $message]);
    }
}
