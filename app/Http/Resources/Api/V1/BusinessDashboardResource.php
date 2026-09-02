<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin array<string, mixed>
 */
class BusinessDashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'business' => $this->resource['business'],
            'date' => $this->resource['date'],
            'generated_at' => $this->resource['generated_at'],
            'today' => $this->resource['today'],
            'upcoming' => $this->resource['upcoming'],
            'operations' => $this->resource['operations'],
            'resources' => $this->resource['resources'],
            'revenue_today' => $this->resource['revenue_today'],
            'upcoming_reservations' => $this->resource['upcoming_reservations'],
        ];
    }
}
