<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyReward */
class LoyaltyRewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'name' => $this->name,
            'description' => $this->description,
            'points_cost' => $this->points_cost,
            'reward_type' => $this->reward_type?->value,
            'value' => $this->value,
            'currency' => $this->currency,
            'stock' => $this->when($request->user()?->isPlatformAdmin() || $request->is('api/v1/manage/*'), $this->stock),
            'max_redemptions_per_user' => $this->max_redemptions_per_user,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_available' => $this->isAvailable(),
        ];
    }
}
