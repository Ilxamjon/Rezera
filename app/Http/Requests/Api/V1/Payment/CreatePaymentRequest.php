<?php

namespace App\Http\Requests\Api\V1\Payment;

use App\Domain\Payments\Enums\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
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
            'provider' => ['required', Rule::enum(PaymentProvider::class)],
        ];
    }
}
