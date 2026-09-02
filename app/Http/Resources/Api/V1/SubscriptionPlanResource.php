<?php

namespace App\Http\Resources\Api\V1;

use App\Models\PlanEntitlement;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SubscriptionPlan */
class SubscriptionPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->localizedName(),
            'description' => $this->localizedDescription(),
            'monthly_price' => $this->monthly_price,
            'yearly_price' => $this->yearly_price,
            'currency' => $this->currency,
            'trial_days' => $this->trial_days,
            'features' => $this->when(
                $this->relationLoaded('entitlements'),
                fn () => $this->entitlements->map(fn (PlanEntitlement $entitlement): array => [
                    'code' => $entitlement->feature_code,
                    'value' => $entitlement->parsedValue(),
                ])->values()->all(),
            ),
        ];
    }
}
