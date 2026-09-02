<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FavoriteBusinessStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'business_id' => $this->resource['business_id'],
            'is_favorite' => (bool) $this->resource['is_favorite'],
        ];
    }
}
