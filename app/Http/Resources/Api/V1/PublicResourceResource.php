<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Resource
 */
class PublicResourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'image' => $this->image_url,
            'resource_type' => $this->resource_type?->value,
            'capacity' => $this->capacity,
            'price' => $this->hourly_rate_amount,
            'price_unit' => $this->rate_unit?->value ?? 'hour',
            'currency' => $this->currency,
            'metadata' => $this->metadata,
            'status' => $this->status?->value,
            'category' => new ResourceCategoryResource($this->whenLoaded('group')),
        ];
    }
}
