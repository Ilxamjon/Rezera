<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BusinessMemberInvitation */
class BusinessMemberInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'phone' => $this->phone,
            'member_role' => $this->member_role?->value,
            'job_title' => $this->job_title,
            'status' => $this->status?->value,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'business' => $this->whenLoaded('business', fn () => [
                'id' => $this->business?->id,
                'name' => $this->business?->name,
            ]),
            'invited_by' => $this->whenLoaded('invitedBy', fn () => $this->invitedBy ? [
                'id' => $this->invitedBy->id,
                'name' => $this->invitedBy->name,
            ] : null),
        ];
    }
}
