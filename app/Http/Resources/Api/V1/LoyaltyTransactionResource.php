<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\LoyaltyTransaction */
class LoyaltyTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'type' => $this->type?->value,
            'points' => $this->points,
            'balance_after' => $this->balance_after,
            'description' => $this->description,
            'source' => $this->source_type !== null ? [
                'type' => $this->source_type,
                'id' => $this->source_id,
            ] : null,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
