<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\Concerns\NormalizesPhoneInput;
use App\Rules\ValidUzbekPhone;
use Illuminate\Foundation\Http\FormRequest;

class RequestOtpRequest extends FormRequest
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
            'purpose' => ['sometimes', 'string', 'in:login'],
        ];
    }
}
