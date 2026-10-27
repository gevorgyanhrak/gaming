<?php

namespace App\Platform\Sms;

use Illuminate\Notifications\Notification;

/**
 * Notification channel "sms": delivers a notification's toSms() text through the configured gateway.
 */
class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (! is_string($to) || $to === '' || ! method_exists($notification, 'toSms')) {
            return;
        }

        $this->gateway->send($to, $notification->toSms($notifiable));
    }
}
