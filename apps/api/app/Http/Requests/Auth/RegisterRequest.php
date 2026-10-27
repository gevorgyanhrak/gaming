<?php

namespace App\Http\Requests\Auth;

use App\Enums\ContactChannel;
use App\Models\ContentPage;
use App\Platform\Auth\AccountRules;
use App\Platform\Settings\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    private ?bool $requiresTerms = null;

    /** Closed registration is refused before any validation. */
    public function authorize(Settings $settings): bool
    {
        return (bool) $settings->get('access.registration_enabled');
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(__('platform.auth.registration_closed'));
    }

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
            // Required once a terms of service page is published.
            'accept_terms' => $this->requiresTerms() ? ['required', 'accepted'] : ['sometimes', 'boolean'],
        ];
    }

    public function requiresTerms(): bool
    {
        return $this->requiresTerms ??= ContentPage::query()->published()->where('slug', ContentPage::TERMS)->exists();
    }

    public function channel(): ContactChannel
    {
        return $this->enum('method', ContactChannel::class) ?? ContactChannel::Email;
    }
}
