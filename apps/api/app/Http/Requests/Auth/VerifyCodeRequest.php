<?php

namespace App\Http\Requests\Auth;

class VerifyCodeRequest extends SendVerificationRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'digits:6'],
        ];
    }
}
