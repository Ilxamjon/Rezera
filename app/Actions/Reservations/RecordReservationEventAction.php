<?php

namespace App\Actions\Reservations;

use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Reservations\ReservationTransitionService;
use Illuminate\Support\Facades\DB;

final class RecordReservationEventAction
{
    public function execute(
        Reservation $reservation,
        ?ReservationStatus $fromStatus,
        ReservationStatus $toStatus,
        ?User $actor,
        ReservationEventSource $source,
        ?string $reason = null,
    ): void {
        $reservation->events()->create([
            'from_status' => $fromStatus?->value,
            'to_status' => $toStatus->value,
            'actor_user_id' => $actor?->id,
            'source' => $source,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
