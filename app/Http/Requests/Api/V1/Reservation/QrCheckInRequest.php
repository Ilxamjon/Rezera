<?php

namespace App\Http\Requests\Api\V1\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class QrCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'qr_token' => ['required', 'string', 'min:32', 'max:64'],
        ];
    }
}
