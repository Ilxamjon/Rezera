<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SavedSearch
 */
class SavedSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'filters' => [
                'search_query' => $this->search_query,
                'category_id' => $this->category_id,
                'city' => $this->city,
                'district' => $this->district,
                'region' => $this->region,
                'country_code' => $this->country_code,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'radius_km' => $this->radius_km,
                'business_id' => $this->business_id,
                'resource_category_id' => $this->resource_category_id,
                'resource_id' => $this->resource_id,
                'resource_type' => $this->resource_type,
                'min_price' => $this->min_price,
                'max_price' => $this->max_price,
                'currency' => $this->currency,
                'date_mode' => $this->date_mode?->value,
                'specific_date' => $this->specific_date?->toDateString(),
                'date_from' => $this->date_from?->toDateString(),
                'date_to' => $this->date_to?->toDateString(),
                'days_of_week' => $this->days_of_week,
                'start_time' => $this->start_time,
                'end_time' => $this->end_time,
                'availability_required' => (bool) $this->availability_required,
            ],
            'alert' => [
                'enabled' => (bool) $this->alert_enabled,
                'channel' => $this->alert_channel?->value,
                'frequency' => $this->alert_frequency?->value,
            ],
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'last_notified_at' => $this->last_notified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
