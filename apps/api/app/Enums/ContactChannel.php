<?php

namespace App\Enums;

/**
 * A way to reach a user: an email address or a phone number (delivered by SMS).
 */
enum ContactChannel: string
{
    case Email = 'email';
    case Phone = 'phone';

    /** The users table column holding this contact. */
    public function column(): string
    {
        return $this->value;
    }

    public function verifiedAtColumn(): string
    {
        return $this->value.'_verified_at';
    }

    /** The notification route used to deliver a message to this contact. */
    public function notificationRoute(): string
    {
        return match ($this) {
            self::Email => 'mail',
            self::Phone => 'sms',
        };
    }
}
