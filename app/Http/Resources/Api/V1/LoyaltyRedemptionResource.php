<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyRedemption */
class LoyaltyRedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'reward' => $this->whenLoaded('reward', fn () => new LoyaltyRewardResource($this->reward)),
            'points_spent' => $this->points_spent,
            'status' => $this->status?->value,
            'redemption_code' => $this->redemption_code,
            'redeemed_at' => $this->redeemed_at?->toIso8601String(),
            'used_at' => $this->used_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
