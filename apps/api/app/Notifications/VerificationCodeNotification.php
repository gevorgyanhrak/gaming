<?php

namespace App\Notifications;

use App\Enums\VerificationPurpose;
use App\Platform\Sms\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly VerificationPurpose $purpose,
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Sent to exactly one route: the email address or phone number being verified.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable && isset($notifiable->routes['sms'])) {
            return [SmsChannel::class];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('platform.verification.code_ttl_minutes');

        return (new MailMessage)
            ->subject(__("platform.codes.{$this->purpose->value}.subject", ['app' => config('app.name')]))
            ->line(__("platform.codes.{$this->purpose->value}.intro"))
            ->line(__('platform.codes.code_line', ['code' => $this->code]))
            ->line(__('platform.codes.expires', ['minutes' => $minutes]))
            ->line(__('platform.codes.ignore'));
    }

    public function toSms(object $notifiable): string
    {
        return __('platform.codes.sms', ['app' => config('app.name'), 'code' => $this->code]);
    }
}
