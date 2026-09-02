<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BusinessCategory */
class AdminCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'image_url' => $this->image_url,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'businesses_count' => $this->whenCounted('businesses'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
