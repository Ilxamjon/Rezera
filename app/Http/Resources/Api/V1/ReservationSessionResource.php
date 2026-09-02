<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Reservations\ReservationOperationalStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Models\ReservationSession $session */
        $session = $this->resource;
        $metadata = $session->metadata ?? [];

        return [
            'id' => $session->id,
            'status' => $session->status?->value,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'start_method' => $session->start_method?->value,
            'end_method' => $session->end_method?->value,
            'scheduled_duration_minutes' => $metadata['scheduled_duration_minutes'] ?? null,
            'actual_duration_minutes' => $metadata['actual_duration_minutes'] ?? null,
            'overtime_minutes' => $metadata['overtime_minutes'] ?? null,
            'reservation' => $this->whenLoaded('reservation', fn () => [
                'id' => $session->reservation?->id,
                'reservation_number' => $session->reservation?->reservation_number,
                'scheduled_end_at' => $session->reservation?->end_at?->toIso8601String(),
            ]),
            'customer' => $this->whenLoaded('user', fn () => [
                'id' => $session->user?->id,
                'name' => $session->user?->name,
            ]),
            'resource' => $this->whenLoaded('resource', fn () => [
                'id' => $session->resource?->id,
                'name' => $session->resource?->name,
                'code' => $session->resource?->code,
            ]),
        ];
    }
}
