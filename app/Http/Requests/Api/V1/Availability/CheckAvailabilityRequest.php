<?php

namespace App\Http\Requests\Api\V1\Availability;

use App\Models\Business;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Rules\ResourceCategoryBelongsToBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Business $business */
        $business = $this->route('business');

        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'category_id' => ['nullable', 'uuid', new ResourceCategoryBelongsToBusiness($business->id)],
            'resource_category_id' => ['nullable', 'uuid', new ResourceCategoryBelongsToBusiness($business->id)],
            'resource_id' => ['nullable', 'uuid', 'exists:resources,id'],
            'include_unavailable' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            if (! is_string($start) || ! is_string($end)) {
                return;
            }

            if ($start === $end) {
                $validator->errors()->add('end_time', __('availability.end_must_differ'));
            }

            $durationMinutes = $this->calculateDurationMinutes($start, $end);
            $maxDuration = (int) config('rezera.availability.max_duration_minutes', 1440);

            if ($durationMinutes <= 0) {
                $validator->errors()->add('end_time', __('availability.invalid_time_range'));
            }

            if ($durationMinutes > $maxDuration) {
                $validator->errors()->add('end_time', __('availability.duration_too_long'));
            }

            if ($this->filled('date')) {
                /** @var Business $business */
                $business = $this->route('business');
                $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
                $requestDate = CarbonImmutable::parse($this->string('date')->toString(), $timezone)->startOfDay();
                $today = CarbonImmutable::now($timezone)->startOfDay();

                if ($requestDate->lessThan($today)) {
                    $validator->errors()->add('date', __('availability.past_date'));
                }
            }

            if ($this->filled('date') && $this->isPastInterval()) {
                $validator->errors()->add('start_time', __('availability.past_time'));
            }

            if ($this->filled('resource_id')) {
                $this->validateResourceBelongsToBusiness($validator);
            }

            if ($this->filled('category_id') && $this->filled('resource_id')) {
                $this->validateResourceInCategory($validator);
            }
        });
    }

    public function resolvedCategoryId(): ?string
    {
        return $this->input('category_id') ?? $this->input('resource_category_id');
    }

    public function resolvedResourceId(): ?string
    {
        /** @var Resource|null $routeResource */
        $routeResource = $this->route('resource');

        return $routeResource?->id ?? $this->input('resource_id');
    }

    public function includeUnavailable(): bool
    {
        return $this->boolean('include_unavailable');
    }

    private function calculateDurationMinutes(string $start, string $end): int
    {
        [$startHour, $startMinute] = array_map(intval(...), explode(':', $start));
        [$endHour, $endMinute] = array_map(intval(...), explode(':', $end));

        $startTotal = ($startHour * 60) + $startMinute;
        $endTotal = ($endHour * 60) + $endMinute;

        if ($endTotal > $startTotal) {
            return $endTotal - $startTotal;
        }

        return (1440 - $startTotal) + $endTotal;
    }

    private function isPastInterval(): bool
    {
        /** @var Business $business */
        $business = $this->route('business');
        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $date = $this->string('date')->toString();
        $start = $this->string('start_time')->toString();
        $end = $this->string('end_time')->toString();

        $startAt = CarbonImmutable::parse("{$date} {$start}", $timezone);
        $endAt = CarbonImmutable::parse("{$date} {$end}", $timezone);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $endAt->addDay();
        }

        return $endAt->lessThanOrEqualTo(CarbonImmutable::now($timezone));
    }

    private function validateResourceBelongsToBusiness(Validator $validator): void
    {
        /** @var Business $business */
        $business = $this->route('business');
        $resourceId = $this->resolvedResourceId();

        $exists = Resource::query()
            ->where('id', $resourceId)
            ->where('business_id', $business->id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $exists) {
            $validator->errors()->add('resource_id', __('availability.resource_not_in_business'));
        }
    }

    private function validateResourceInCategory(Validator $validator): void
    {
        $resourceId = $this->resolvedResourceId();
        $categoryId = $this->resolvedCategoryId();

        $matches = Resource::query()
            ->where('id', $resourceId)
            ->where('resource_group_id', $categoryId)
            ->exists();

        if (! $matches) {
            $validator->errors()->add('resource_id', __('availability.resource_not_in_category'));
        }
    }
}
