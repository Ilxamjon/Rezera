<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneInput;
use App\Rules\ValidUzbekPhone;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ];
    }
}
