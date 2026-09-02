<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyProgram */
class LoyaltyProgramResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'name' => $this->name,
            'description' => $this->description,
            'is_enabled' => $this->is_enabled,
            'earn_rate_points' => $this->earn_rate_points,
            'earn_amount' => $this->earn_amount,
            'flat_points_per_reservation' => $this->flat_points_per_reservation,
            'minimum_qualifying_amount' => $this->minimum_qualifying_amount,
            'max_points_per_transaction' => $this->max_points_per_transaction,
            'points_expiration_days' => $this->points_expiration_days,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
