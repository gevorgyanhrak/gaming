<?php

namespace App\Http\Requests\Auth;

use App\Platform\Auth\LoginIdentifier;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (LoginIdentifier::parse((string) $value) === null) {
                    $fail(__('platform.auth.invalid_login'));
                }
            }],
        ];
    }

    public function identifier(): LoginIdentifier
    {
        /** @var LoginIdentifier */
        return LoginIdentifier::parse($this->string('login')->toString());
    }
}
