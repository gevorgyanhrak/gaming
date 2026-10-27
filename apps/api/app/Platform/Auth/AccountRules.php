<?php

namespace App\Platform\Auth;

use App\Models\User;
use Closure;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Validation rules for account identifiers, shared by registration and account settings.
 * Inputs must be normalized first (see normalize()) so uniqueness is checked on stored forms.
 */
final class AccountRules
{
    /**
     * @return list<mixed>
     */
    public static function username(?User $ignore = null): array
    {
        return [
            'required', 'string', 'min:3', 'max:20',
            'regex:/^[A-Za-z0-9_]+$/',
            function (string $attribute, mixed $value, Closure $fail) {
                if (in_array(mb_strtolower((string) $value), config('platform.registration.reserved_usernames'), true)) {
                    $fail(__('platform.auth.username_reserved'));
                }
            },
            Rule::unique('users', 'username')->ignore($ignore),
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function email(?User $ignore = null): array
    {
        return ['required', 'string', 'max:255', 'email:rfc', Rule::unique('users', 'email')->ignore($ignore)];
    }

    /**
     * @return list<mixed>
     */
    public static function phone(?User $ignore = null): array
    {
        return [
            'required', 'string', 'max:32',
            (new Phone)->international(),
            function (string $attribute, mixed $value, Closure $fail) {
                $e164 = Phones::normalize((string) $value);

                if ($e164 !== null && ! Phones::isAllowedCountry($e164)) {
                    $fail(__('platform.auth.phone_country'));
                }
            },
            Rule::unique('users', 'phone')->ignore($ignore),
        ];
    }

    /**
     * Normalize identifier inputs in place: emails lower-cased, valid phone numbers to E.164.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }

        if (is_string($input['phone'] ?? null)) {
            $input['phone'] = Phones::normalize($input['phone']) ?? $input['phone'];
        }

        if (is_string($input['username'] ?? null)) {
            $input['username'] = trim($input['username']);
        }

        return $input;
    }
}
