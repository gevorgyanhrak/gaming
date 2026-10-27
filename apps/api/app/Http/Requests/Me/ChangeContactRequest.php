<?php

namespace App\Http\Requests\Me;

use App\Enums\ContactChannel;
use App\Models\User;
use App\Platform\Auth\AccountRules;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeContactRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(AccountRules::normalize($this->only(['email', 'phone'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();
        $notCurrent = function (string $attribute, mixed $value, Closure $fail) use ($user) {
            if ($value === $user->getAttribute($attribute)) {
                $fail(__('platform.contact.same', ['channel' => __("platform.channels.{$attribute}")]));
            }
        };

        return [
            'channel' => ['required', Rule::enum(ContactChannel::class)],
            'email' => ['exclude_unless:channel,email', ...AccountRules::email($user), $notCurrent],
            'phone' => ['exclude_unless:channel,phone', ...AccountRules::phone($user), $notCurrent],
        ];
    }

    public function channel(): ContactChannel
    {
        /** @var ContactChannel */
        return $this->enum('channel', ContactChannel::class);
    }

    public function destination(): string
    {
        return $this->string($this->channel()->column())->toString();
    }
}
