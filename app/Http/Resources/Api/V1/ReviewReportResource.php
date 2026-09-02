<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ReviewReport
 */
class ReviewReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'review_id' => $this->review_id,
            'reason' => $this->reason?->value,
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
