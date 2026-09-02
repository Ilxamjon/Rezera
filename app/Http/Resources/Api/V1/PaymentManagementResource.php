<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * @mixin \App\Models\Payment
 */
class PaymentManagementResource extends PaymentResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'reservation' => $this->whenLoaded('reservation', fn () => [
                'id' => $this->reservation?->id,
                'reservation_number' => $this->reservation?->reservation_number,
                'status' => $this->reservation?->status?->value,
                'resource' => $this->reservation?->relationLoaded('resource') && $this->reservation?->resource !== null
                    ? [
                        'id' => $this->reservation->resource->id,
                        'name' => $this->reservation->resource->name,
                        'category' => $this->reservation->resource->relationLoaded('group') && $this->reservation->resource->group !== null
                            ? new ResourceCategoryResource($this->reservation->resource->group)
                            : null,
                    ]
                    : null,
            ]),
            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'phone' => $this->user?->phone,
            ]),
            'refunded_at' => $this->refunded_at?->toIso8601String(),
            'failure_reason' => $this->when(
                $this->status?->value === 'failed',
                $this->failure_reason,
            ),
        ];
    }
}
