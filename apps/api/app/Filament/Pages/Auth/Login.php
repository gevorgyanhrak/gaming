<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use SensitiveParameter;

/**
 * Admin login. Emails are stored lower-cased, so the typed address is lower-cased too.
 */
class Login extends BaseLogin
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'email' => mb_strtolower(trim((string) $data['email'])),
            'password' => $data['password'],
        ];
    }
}
