<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Business
 */
class PublicBusinessResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'translations' => $this->translations,
            'phone' => $this->phone,
            'email' => $this->email,
            'country_code' => $this->country_code,
            'region' => $this->region,
            'city' => $this->city,
            'district' => $this->district,
            'address_line' => $this->address_line,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
            'cover_image_url' => $this->cover_image_url,
            'category' => new BusinessCategoryResource($this->whenLoaded('category')),
            'working_hours' => BusinessWorkingHoursResource::collection($this->whenLoaded('hours')),
            'resource_categories' => ResourceCategoryResource::collection($this->whenLoaded('resourceGroups')),
            'resources' => PublicResourceResource::collection($this->whenLoaded('resources')),
            'price_from' => $this->when($this->price_from !== null, (int) $this->price_from),
            'currency' => config('rezera.default_currency', 'UZS'),
            'open_now' => $this->when($this->open_now !== null, (bool) $this->open_now),
            'is_favorite' => $this->when($this->is_favorite !== null, (bool) $this->is_favorite),
            'rating' => new BusinessRatingResource($this->resource),
        ];
    }
}
