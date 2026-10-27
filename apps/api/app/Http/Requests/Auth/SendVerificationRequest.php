<?php

namespace App\Http\Requests\Auth;

use App\Enums\ContactChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendVerificationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(ContactChannel::class)],
        ];
    }

    public function channel(): ContactChannel
    {
        /** @var ContactChannel */
        return $this->enum('channel', ContactChannel::class);
    }
}
