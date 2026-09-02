<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Business
 */
class BusinessManagementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $myRole = $this->my_member_role ?? $this->resolveMyRole($request);

        return [
            ...PublicBusinessResource::make($this)->toArray($request),
            'status' => $this->status?->value,
            'rejection_reason' => $this->rejection_reason,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'my_role' => $myRole instanceof BusinessMemberRole ? $myRole->value : $myRole,
            'booking_policy' => $this->whenLoaded('bookingPolicy', fn () => [
                'confirmation_mode' => $this->bookingPolicy?->confirmation_mode?->value,
            ]),
        ];
    }

    private function resolveMyRole(Request $request): ?string
    {
        if (! $this->relationLoaded('activeMembers')) {
            return null;
        }

        $userId = $request->user()?->id;

        if ($userId === null) {
            return null;
        }

        $membership = $this->activeMembers->firstWhere('user_id', $userId);

        return $membership?->member_role?->value;
    }
}
