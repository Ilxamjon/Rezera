<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Domain\Identity\Enums\Locale;
use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneInput;
use App\Rules\ValidUzbekPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:16', new ValidUzbekPhone, 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'locale' => ['sometimes', 'string', Rule::in(Locale::supported())],
            'device_name' => ['sometimes', 'string', 'max:120'],
            'referral_code' => ['sometimes', 'nullable', 'string', 'max:16'],
        ];
    }
}
