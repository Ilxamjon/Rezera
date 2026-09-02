<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\ReviewAuthorResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Review
 */
class AdminReviewResource extends JsonResource
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
            'business' => $this->whenLoaded('business', fn () => [
                'id' => $this->business->id,
                'name' => $this->business->name,
            ]),
            'author' => new ReviewAuthorResource($this->whenLoaded('user')),
            'hidden_at' => $this->hidden_at?->toIso8601String(),
            'hidden_reason' => $this->hidden_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
