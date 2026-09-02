<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\PricingRule
 */
class PricingRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'resource_id' => $this->resource_id,
            'resource_category_id' => $this->resource_group_id,
            'name' => $this->name,
            'description' => $this->description,
            'pricing_type' => $this->pricing_type?->value,
            'price' => $this->price,
            'currency' => $this->currency,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time ? substr((string) $this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr((string) $this->end_time, 0, 5) : null,
            'specific_date' => $this->specific_date?->format('Y-m-d'),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
