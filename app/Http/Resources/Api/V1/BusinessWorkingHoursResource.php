<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\BusinessHour
 */
class BusinessWorkingHoursResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'weekday' => $this->weekday,
            'is_closed' => $this->is_closed,
            'is_open_24h' => $this->is_open_24h,
            'opens_at' => $this->formatTime($this->opens_at),
            'closes_at' => $this->formatTime($this->closes_at),
        ];
    }

    private function formatTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return strlen($value) >= 5 ? substr($value, 0, 5) : $value;
        }

        return null;
    }
}
