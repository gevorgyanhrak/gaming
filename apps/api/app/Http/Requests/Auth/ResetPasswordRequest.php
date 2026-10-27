<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends ForgotPasswordRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
