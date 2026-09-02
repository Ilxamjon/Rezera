<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SubscriptionPlan */
class AdminSubscriptionPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'translations' => $this->translations,
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,
            'sort_order' => $this->sort_order,
            'monthly_price' => $this->monthly_price,
            'yearly_price' => $this->yearly_price,
            'currency' => $this->currency,
            'trial_days' => $this->trial_days,
            'metadata' => $this->metadata,
            'entitlements' => $this->whenLoaded('entitlements', fn () => $this->entitlements->map(fn ($entitlement) => [
                'feature_code' => $entitlement->feature_code,
                'value_type' => $entitlement->value_type?->value,
                'value' => $entitlement->parsedValue(),
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
