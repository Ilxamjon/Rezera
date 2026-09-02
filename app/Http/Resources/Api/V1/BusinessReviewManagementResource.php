<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Review
 */
class BusinessReviewManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status?->value,
            'author' => new ReviewAuthorResource($this->whenLoaded('user')),
            'reservation_id' => $this->reservation_id,
            'business_response' => $this->when(
                $this->business_response !== null,
                fn (): array => [
                    'body' => $this->business_response,
                    'responded_at' => $this->business_responded_at?->toIso8601String(),
                    'responder' => new ReviewAuthorResource($this->whenLoaded('businessResponder')),
                ],
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
        ];
    }
}
