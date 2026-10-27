<?php

namespace App\Platform\Auth;

use App\Enums\ContactChannel;
use App\Models\User;

/**
 * What a user typed to identify themselves: an email address or a phone number.
 */
final readonly class LoginIdentifier
{
    private function __construct(
        public ContactChannel $channel,
        public string $value,
    ) {}

    /** Null when the input is neither an email address nor a valid international phone number. */
    public static function parse(string $input): ?self
    {
        $input = trim($input);

        if (str_contains($input, '@')) {
            return filter_var($input, FILTER_VALIDATE_EMAIL) === false
                ? null
                : new self(ContactChannel::Email, mb_strtolower($input));
        }

        $phone = Phones::normalize($input);

        return $phone === null ? null : new self(ContactChannel::Phone, $phone);
    }

    public function findUser(): ?User
    {
        return User::query()->where($this->channel->column(), $this->value)->first();
    }

    /**
     * @return array<string, string>
     */
    public function credentials(string $password): array
    {
        return [$this->channel->column() => $this->value, 'password' => $password];
    }
}
