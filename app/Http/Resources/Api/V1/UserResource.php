<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $memberships = $this->relationLoaded('businessMemberships')
            ? $this->businessMemberships
            : collect();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'locale' => $this->locale?->value,
            'platform_role' => $this->platform_role?->value,
            'status' => $this->status?->value,
            'avatar_url' => $this->avatar_url,
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'business_memberships' => BusinessMembershipResource::collection($memberships),
            'capabilities' => [
                'is_platform_admin' => $this->isPlatformAdmin(),
                'has_business_memberships' => $memberships->isNotEmpty(),
            ],
        ];
    }
}
