<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessOnboardingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'],
            'percentage' => $this->resource['percentage'],
            'completed_count' => $this->resource['completed_count'],
            'required_count' => $this->resource['required_count'],
            'completed_steps' => $this->resource['completed_steps'],
            'remaining_steps' => $this->resource['remaining_steps'],
            'next_step' => $this->resource['next_step'],
            'ready_for_verification' => $this->resource['ready_for_verification'],
            'ready_for_reservations' => $this->resource['ready_for_reservations'],
            'verification_status' => $this->resource['verification_status'],
            'onboarding_completed_at' => $this->resource['onboarding_completed_at'],
            'steps' => $this->resource['steps'],
        ];
    }
}
