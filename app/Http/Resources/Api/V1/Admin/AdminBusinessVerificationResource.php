<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\BusinessVerificationResource;
use App\Models\BusinessVerification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BusinessVerification */
class AdminBusinessVerificationResource extends JsonResource
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
            'rejection_reason' => $this->rejection_reason,
            'admin_notes' => $this->admin_notes,
            'business' => $this->whenLoaded('business', fn () => [
                'id' => $this->business->id,
                'name' => $this->business->name,
                'city' => $this->business->city,
                'status' => $this->business->status?->value,
                'verification_status' => $this->business->verification_status?->value,
                'onboarding_status' => $this->business->onboarding_status?->value,
                'category' => $this->business->category?->only(['id', 'slug']),
            ]),
            'submitted_by' => $this->whenLoaded('submittedBy', fn () => [
                'id' => $this->submittedBy->id,
                'name' => $this->submittedBy->name,
            ]),
            'reviewed_by' => $this->whenLoaded('reviewedBy', fn () => $this->reviewedBy ? [
                'id' => $this->reviewedBy->id,
                'name' => $this->reviewedBy->name,
            ] : null),
            'history' => $this->when(isset($this->resource['history']), $this->resource['history']),
            'readiness' => $this->when(isset($this->resource['readiness']), $this->resource['readiness']),
        ];
    }
}
