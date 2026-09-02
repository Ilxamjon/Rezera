<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Optimized business card for discovery list responses.
 *
 * @mixin \App\Models\Business
 */
class BusinessDiscoveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $description = $this->description;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_description' => is_string($description)
                ? mb_substr($description, 0, 160)
                : null,
            'cover_image_url' => $this->cover_image_url,
            'category' => new BusinessCategoryResource($this->whenLoaded('category')),
            'city' => $this->city,
            'district' => $this->district,
            'address_line' => $this->address_line,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'price_from' => $this->price_from !== null ? (int) $this->price_from : null,
            'currency' => config('rezera.default_currency', 'UZS'),
            'distance_km' => $this->when(
                $this->distance_km !== null,
                fn (): float => round((float) $this->distance_km, 1),
            ),
            'open_now' => $this->when(
                $this->open_now !== null,
                (bool) $this->open_now,
            ),
            'active_resources_count' => $this->when(
                $this->active_resources_count !== null,
                (int) $this->active_resources_count,
            ),
            'is_favorite' => $this->when(
                $this->is_favorite !== null,
                (bool) $this->is_favorite,
            ),
            'rating' => new BusinessRatingResource($this->resource),
        ];
    }
}
