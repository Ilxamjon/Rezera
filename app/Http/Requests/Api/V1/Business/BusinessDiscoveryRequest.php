<?php

namespace App\Http\Requests\Api\V1\Business;

use App\Domain\Discovery\Enums\BusinessDiscoverySort;
use App\Domain\Resources\Enums\ResourceType;
use App\Models\BusinessCategory;
use App\Models\ResourceGroup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BusinessDiscoveryRequest extends FormRequest
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
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'uuid', Rule::exists('business_categories', 'id')->where('is_active', true)],
            'category' => ['nullable', 'string', 'max:120', Rule::exists('business_categories', 'slug')->where('is_active', true)],
            'city' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'radius' => ['nullable', 'numeric', 'min:0.1', 'max:100', 'required_with:latitude,longitude'],
            'open_now' => ['sometimes', 'boolean'],
            'available_now' => ['sometimes', 'boolean'],
            'available_date' => ['nullable', 'date_format:Y-m-d', 'required_with:available_start_time,available_end_time'],
            'available_start_time' => ['nullable', 'date_format:H:i', 'required_with:available_date,available_end_time'],
            'available_end_time' => ['nullable', 'date_format:H:i', 'required_with:available_date,available_start_time', 'different:available_start_time'],
            'resource_category_id' => ['nullable', 'uuid', Rule::exists('resource_groups', 'id')->whereNull('deleted_at')],
            'resource_type' => ['nullable', 'string', Rule::enum(ResourceType::class)],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0', 'gte:min_price'],
            'sort' => ['nullable', 'string', Rule::in(BusinessDiscoverySort::values())],
            'favorites' => ['sometimes', 'boolean'],
            'include_open_now' => ['sometimes', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('available_date') && $this->filled('available_start_time') && $this->filled('available_end_time')) {
                $this->validateAvailabilityWindow($validator);
            }

            if ($this->filled('sort') && $this->string('sort')->toString() === BusinessDiscoverySort::Nearest->value) {
                if (! $this->filled('latitude') || ! $this->filled('longitude')) {
                    $validator->errors()->add('sort', __('discovery.nearest_requires_coordinates'));
                }
            }

            if ($this->filled('resource_category_id')) {
                $group = ResourceGroup::query()
                    ->where('id', $this->string('resource_category_id')->toString())
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->first();

                if ($group === null) {
                    $validator->errors()->add('resource_category_id', __('discovery.resource_category_not_found'));
                }
            }
        });
    }

    public function normalizedSearch(): ?string
    {
        $value = $this->input('q') ?? $this->input('search');

        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        return $normalized === '' ? null : $normalized;
    }

    public function resolvedCategoryId(): ?string
    {
        if ($this->filled('category_id')) {
            return $this->string('category_id')->toString();
        }

        if ($this->filled('category')) {
            return BusinessCategory::query()
                ->where('slug', $this->string('category')->toString())
                ->where('is_active', true)
                ->value('id');
        }

        return null;
    }

    public function resolvedResourceCategoryId(): ?string
    {
        return $this->filled('resource_category_id')
            ? $this->string('resource_category_id')->toString()
            : null;
    }

    public function resolvedSort(): BusinessDiscoverySort
    {
        if (! $this->filled('sort')) {
            return $this->normalizedSearch() !== null
                ? BusinessDiscoverySort::Relevance
                : BusinessDiscoverySort::Newest;
        }

        return BusinessDiscoverySort::from($this->string('sort')->toString());
    }

    private function validateAvailabilityWindow(Validator $validator): void
    {
        $date = $this->string('available_date')->toString();
        $start = $this->string('available_start_time')->toString();
        $end = $this->string('available_end_time')->toString();

        if ($start === $end) {
            $validator->errors()->add('available_end_time', __('availability.end_must_differ'));
        }

        $durationMinutes = $this->calculateDurationMinutes($start, $end);
        $maxDuration = (int) config('rezera.availability.max_duration_minutes', 1440);

        if ($durationMinutes <= 0) {
            $validator->errors()->add('available_end_time', __('availability.invalid_time_range'));
        }

        if ($durationMinutes > $maxDuration) {
            $validator->errors()->add('available_end_time', __('availability.duration_too_long'));
        }

        $timezone = config('rezera.default_business_timezone', 'Asia/Tashkent');
        $requestDate = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if ($requestDate->lessThan($today)) {
            $validator->errors()->add('available_date', __('availability.past_date'));
        }

        $startAt = CarbonImmutable::parse("{$date} {$start}", $timezone);
        $endAt = CarbonImmutable::parse("{$date} {$end}", $timezone);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt = $endAt->addDay();
        }

        if ($endAt->lessThanOrEqualTo(CarbonImmutable::now($timezone))) {
            $validator->errors()->add('available_start_time', __('availability.past_time'));
        }
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
}
