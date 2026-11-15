<?php

namespace App\Http\Requests\Slots;

use Illuminate\Foundation\Http\FormRequest;

/** A provider's wallet call (the signature was checked before this). */
class SlotWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:balance,bet,win,rollback'],
            'session' => ['required', 'string', 'max:100'],
            'tx_id' => ['required_unless:action,balance', 'nullable', 'string', 'max:120'],
            'round_id' => ['required_if:action,bet,win', 'nullable', 'string', 'max:120'],
            'amount' => ['required_if:action,bet,win', 'nullable', 'integer', 'min:0', 'max:1000000000'],
            'reference_tx_id' => ['required_if:action,rollback', 'nullable', 'string', 'max:120'],
        ];
    }
}
