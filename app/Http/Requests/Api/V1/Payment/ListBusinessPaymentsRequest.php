<?php

namespace App\Http\Requests\Api\V1\Payment;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListBusinessPaymentsRequest extends FormRequest
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
        /** @var Business $business */
        $business = $this->route('business');

        return [
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'provider' => ['nullable', Rule::enum(PaymentProvider::class)],
            'reservation_id' => ['nullable', 'uuid', 'exists:reservations,id'],
            'resource_id' => [
                'nullable',
                'uuid',
                Rule::exists('resources', 'id')->where(fn ($query) => $query->where('business_id', $business->id)),
            ],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'required_with:date_to'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'required_with:date_from', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
