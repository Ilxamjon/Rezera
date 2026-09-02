<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Business */
class AdminBusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status?->value,
            'is_publicly_listed' => $this->is_publicly_listed,
            'verification_status' => $this->verification_status?->value,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'slug' => $this->category?->slug,
            ]),
            'owner' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ]),
            'city' => $this->city,
            'region' => $this->region,
            'phone' => $this->phone,
            'email' => $this->email,
            'resources_count' => $this->whenCounted('resources'),
            'reservations_count' => $this->whenCounted('reservations'),
            'favorites_count' => $this->whenCounted('favorites'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
