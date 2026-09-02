<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BusinessSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BusinessSubscription */
class BusinessSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'billing_interval' => $this->billing_interval?->value,
            'plan' => $this->whenLoaded('plan', fn () => new SubscriptionPlanResource($this->plan)),
            'pending_plan' => $this->whenLoaded('pendingPlan', fn () => $this->pendingPlan
                ? new SubscriptionPlanResource($this->pendingPlan)
                : null),
            'started_at' => $this->started_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_start' => $this->current_period_start?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_at_period_end' => $this->cancel_at_period_end?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'renewal' => [
                'next_billing_at' => $this->current_period_end?->toIso8601String(),
                'scheduled_cancellation' => $this->isScheduledForCancellation(),
            ],
        ];
    }
}
