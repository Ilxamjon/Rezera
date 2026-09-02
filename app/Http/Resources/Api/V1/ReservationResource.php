<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Reservations\ReservationOperationalStatusResolver;
use App\Support\Reservations\ReservationIntervalResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Reservation
 */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = $this->business?->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $resolver = app(ReservationIntervalResolver::class);
        $operationalResolver = app(ReservationOperationalStatusResolver::class);
        $interval = null;

        if ($this->start_at !== null && $this->end_at !== null) {
            $interval = new \App\Support\Time\TimeInterval(
                \Carbon\CarbonImmutable::instance($this->start_at)->utc(),
                \Carbon\CarbonImmutable::instance($this->end_at)->utc(),
            );
        }

        return [
            'id' => $this->id,
            'reservation_number' => $this->reservation_number,
            'status' => $this->status?->value,
            'operational_status' => $operationalResolver->resolve($this->resource)->value,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            'check_in_method' => $this->check_in_method?->value,
            'check_out_method' => $this->check_out_method?->value,
            'session' => $this->when(
                $this->relationLoaded('activeSession') || $this->relationLoaded('latestSession'),
                fn () => new ReservationSessionResource($this->activeSession ?? $this->latestSession),
            ),
            'business' => $this->whenLoaded('business', fn () => [
                'id' => $this->business?->id,
                'name' => $this->business?->name,
            ]),
            'resource' => $this->whenLoaded('resource', fn () => [
                'id' => $this->resource?->id,
                'name' => $this->resource?->name,
                'code' => $this->resource?->code,
                'category' => $this->resource?->relationLoaded('group') && $this->resource?->group !== null
                    ? new ResourceCategoryResource($this->resource->group)
                    : null,
            ]),
            'date' => $interval ? $resolver->localDate($interval, $timezone) : null,
            'start_time' => $interval ? $resolver->localStartTime($interval, $timezone) : null,
            'end_time' => $interval ? $resolver->localEndTime($interval, $timezone) : null,
            'duration_minutes' => $this->duration_minutes,
            'hourly_rate_amount' => $this->hourly_rate_amount,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'promo' => $this->when($this->promo_code_snapshot !== null, fn () => [
                'code' => $this->promo_code_snapshot,
                'discount_type' => $this->discount_type,
                'discount_value' => $this->discount_value_snapshot,
            ]),
            'payment_status' => $this->payment_status?->value,
            'notes' => $this->notes,
            'cancellation_reason' => $this->when(
                $this->status?->value === 'cancelled',
                $this->cancellation_reason,
            ),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
