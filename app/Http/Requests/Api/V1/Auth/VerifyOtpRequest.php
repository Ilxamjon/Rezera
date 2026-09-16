<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneInput;
use App\Rules\ValidUzbekPhone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    use NormalizesPhoneInput;

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
            'phone' => ['required', 'string', 'max:16', new ValidUzbekPhone],
            'code' => ['required', 'string', 'size:6'],
            'name' => ['nullable', 'string', 'max:120'],
            'device_name' => ['nullable', 'string', 'max:80'],
            'purpose' => ['sometimes', 'string', 'in:login'],
        ];
    }
}
