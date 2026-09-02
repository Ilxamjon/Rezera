<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\ResolvesLocalizedContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\BusinessCategory
 */
class BusinessCategoryResource extends JsonResource
{
    use ResolvesLocalizedContent;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'localized_name' => $this->localized($this->name, $request),
            'description' => $this->description,
            'localized_description' => $this->localized($this->description, $request),
            'icon' => $this->icon,
            'image_url' => $this->image_url,
            'sort_order' => $this->sort_order,
            'business_count' => $this->when($this->business_count !== null, (int) $this->business_count),
        ];
    }
}
