<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Models\Business;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessWorkingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Business|null $business */
        $business = $this->route('business');

        return $business !== null && $this->user()?->can('manageWorkingHours', $business);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'working_hours' => ['required', 'array', 'size:7'],
            'working_hours.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'working_hours.*.is_closed' => ['required', 'boolean'],
            'working_hours.*.is_open_24h' => ['required', 'boolean'],
            'working_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'working_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->input('working_hours', []) as $index => $hour) {
                $isClosed = (bool) ($hour['is_closed'] ?? false);
                $is24h = (bool) ($hour['is_open_24h'] ?? false);
                $opens = $hour['opens_at'] ?? null;
                $closes = $hour['closes_at'] ?? null;

                if ($isClosed && ($is24h || $opens !== null || $closes !== null)) {
                    $validator->errors()->add("working_hours.$index.is_closed", __('business.invalid_closed_day'));
                }

                if ($is24h && ($isClosed || $opens !== null || $closes !== null)) {
                    $validator->errors()->add("working_hours.$index.is_open_24h", __('business.invalid_24h_day'));
                }

                if (! $isClosed && ! $is24h && ($opens === null || $closes === null)) {
                    $validator->errors()->add("working_hours.$index.opens_at", __('business.opens_closes_required'));
                }
            }
        });
    }
}
