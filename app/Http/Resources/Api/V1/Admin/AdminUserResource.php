<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'locale' => $this->locale?->value,
            'platform_role' => $this->platform_role?->value,
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'reservations_count' => $this->whenCounted('reservations'),
            'business_memberships_count' => $this->whenCounted('businessMemberships'),
        ];
    }
}
