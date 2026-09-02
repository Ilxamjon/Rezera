<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\Business;
use App\Rules\ResourceBelongsToBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

trait ValidatesReservationInterval
{
    protected function reservationIntervalRules(Business $business): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
        ];
    }

    public function withReservationIntervalValidator(Validator $validator, Business $business): void
    {
        $validator->after(function (Validator $validator) use ($business): void {
            $start = $this->input('start_time');
            $end = $this->input('end_time');

            if (! is_string($start) || ! is_string($end)) {
                return;
            }

            $durationMinutes = $this->calculateReservationDurationMinutes($start, $end);
            $maxDuration = (int) config('rezera.availability.max_duration_minutes', 1440);

            if ($durationMinutes <= 0) {
                $validator->errors()->add('end_time', __('availability.invalid_time_range'));
            }

            if ($durationMinutes > $maxDuration) {
                $validator->errors()->add('end_time', __('availability.duration_too_long'));
            }

            if ($this->filled('date')) {
                $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
                $requestDate = CarbonImmutable::parse($this->string('date')->toString(), $timezone)->startOfDay();
                $today = CarbonImmutable::now($timezone)->startOfDay();

                if ($requestDate->lessThan($today)) {
                    $validator->errors()->add('date', __('availability.past_date'));
                }
            }

            if ($this->filled('date') && $this->isPastReservationInterval($business)) {
                $validator->errors()->add('start_time', __('availability.past_time'));
            }
        });
    }

    protected function calculateReservationDurationMinutes(string $start, string $end): int
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

    protected function isPastReservationInterval(Business $business): bool
    {
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
}
