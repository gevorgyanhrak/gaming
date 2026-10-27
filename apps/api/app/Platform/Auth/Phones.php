<?php

namespace App\Platform\Auth;

use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

/**
 * Phone numbers are accepted in international format and stored as E.164.
 */
final class Phones
{
    /** E.164 form of a valid international number, or null. */
    public static function normalize(string $input): ?string
    {
        try {
            $phone = new PhoneNumber(trim($input));

            return $phone->isValid() ? $phone->formatE164() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Whether the number's country is allowed for registration (see platform.phone.allowed_countries). */
    public static function isAllowedCountry(string $e164): bool
    {
        /** @var list<string> $allowed */
        $allowed = config('platform.phone.allowed_countries');

        return $allowed === [] || (new PhoneNumber($e164))->isOfCountry($allowed);
    }
}
