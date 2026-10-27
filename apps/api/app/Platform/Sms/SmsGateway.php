<?php

namespace App\Platform\Sms;

/**
 * Sends text messages. One implementation per provider; selected by services.sms.driver.
 *
 * Only the "log" driver exists so far. A real provider (Twilio, Vonage or a regional
 * aggregator) is added once the launch countries are decided; until then phone
 * verification cannot be used in production (LogSmsGateway refuses to run there).
 */
interface SmsGateway
{
    /**
     * @param  string  $to  Destination in E.164 format.
     */
    public function send(string $to, string $message): void;
}
