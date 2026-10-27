<?php

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => [
                'required', 'file', 'image', 'mimes:jpeg,png,webp',
                'max:'.config('platform.avatars.max_upload_kb'),
                // Checked from the image header before decoding, which also bounds memory use.
                'dimensions:min_width=64,min_height=64,max_width=4096,max_height=4096',
            ],
        ];
    }
}
