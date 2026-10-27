<?php

namespace App\Http\Requests\Auth;

use App\Enums\ContactChannel;
use App\Platform\Auth\AccountRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(AccountRules::normalize($this->only(['email', 'phone', 'username'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Only the identifier for the chosen method is required (and accepted).
            'method' => ['required', Rule::enum(ContactChannel::class)],
            'email' => ['exclude_unless:method,email', ...AccountRules::email()],
            'phone' => ['exclude_unless:method,phone', ...AccountRules::phone()],
            'username' => AccountRules::username(),
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function channel(): ContactChannel
    {
        return $this->enum('method', ContactChannel::class) ?? ContactChannel::Email;
    }
}
