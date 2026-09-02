<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyAccount */
class LoyaltyAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'business_id' => $this->business_id,
            'program' => $this->when(
                $this->relationLoaded('program') && $this->program !== null,
                fn () => [
                    'name' => $this->program->name,
                    'is_enabled' => $this->program->is_enabled,
                ],
            ),
            'balance' => $this->balance,
            'lifetime_earned' => $this->lifetime_earned,
            'lifetime_redeemed' => $this->lifetime_redeemed,
            'status' => $this->status?->value,
        ];
    }
}
