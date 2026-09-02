<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Resource
 */
class ResourceManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'resource_category_id' => $this->resource_group_id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'image' => $this->image_url,
            'resource_type' => $this->resource_type?->value,
            'status' => $this->status?->value,
            'capacity' => $this->capacity,
            'price' => $this->hourly_rate_amount,
            'price_unit' => $this->rate_unit?->value ?? 'hour',
            'currency' => $this->currency,
            'metadata' => $this->metadata,
            'sort_order' => $this->sort_order,
            'category' => new ResourceCategoryResource($this->whenLoaded('group')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
