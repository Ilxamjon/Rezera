<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\BusinessMember
 */
class BusinessMembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'business_id' => $this->business_id,
            'business_name' => $this->whenLoaded('business', fn () => $this->business?->name),
            'member_role' => $this->member_role?->value,
            'job_title' => $this->job_title,
            'status' => $this->status?->value,
        ];
    }
}
