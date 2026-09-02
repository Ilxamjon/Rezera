<?php

namespace App\Http\Requests\Api\V1\Calendar;

use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessCalendarDayRequest extends FormRequest
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
            'date' => ['nullable', 'regex:/^(\d{4}-\d{2}-\d{2}|today)$/'],
            'resource_id' => [
                'nullable',
                'uuid',
                Rule::exists('resources', 'id')->where(fn ($query) => $query->where('business_id', $business->id)),
            ],
            'resource_category_id' => [
                'nullable',
                'uuid',
                Rule::exists('resource_groups', 'id')->where(fn ($query) => $query->where('business_id', $business->id)),
            ],
            'include_cancelled' => ['nullable', 'boolean'],
            'hide_available_gaps' => ['nullable', 'boolean'],
        ];
    }
}
