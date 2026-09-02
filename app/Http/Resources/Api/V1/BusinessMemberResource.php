<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BusinessMember */
class BusinessMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'member_role' => $this->member_role?->value,
            'job_title' => $this->job_title,
            'status' => $this->status?->value,
            'joined_at' => $this->joined_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'phone' => $this->user?->phone,
            ]),
            'invited_by' => $this->whenLoaded('invitedBy', fn () => $this->invitedBy ? [
                'id' => $this->invitedBy->id,
                'name' => $this->invitedBy->name,
            ] : null),
        ];
    }
}
