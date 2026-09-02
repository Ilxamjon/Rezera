<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BusinessVerification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BusinessVerification */
class BusinessVerificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'rejection_reason' => $this->when(
                $this->status?->value === 'rejected',
                $this->rejection_reason,
            ),
            'next_action' => $this->resource['next_action'] ?? null,
        ];
    }
}
