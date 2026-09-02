<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\SubscriptionPlanResource;
use App\Models\BusinessSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BusinessSubscription */
class AdminBusinessSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_id' => $this->business_id,
            'status' => $this->status?->value,
            'billing_interval' => $this->billing_interval?->value,
            'provider' => $this->provider,
            'plan' => $this->whenLoaded('plan', fn () => new SubscriptionPlanResource($this->plan)),
            'started_at' => $this->started_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
