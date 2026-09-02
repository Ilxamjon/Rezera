<?php

namespace App\Actions\Reservations;

use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Events\Reservations\ReservationStatusChanged;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Reservations\ReservationTransitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeReservationStatusAction
{
    public function __construct(
        private readonly ReservationTransitionService $transitionService,
        private readonly RecordReservationEventAction $recordEvent,
    ) {}

    public function execute(
        Reservation $reservation,
        ReservationStatus $toStatus,
        User $actor,
        ReservationEventSource $source,
        ?string $reason = null,
    ): Reservation {
        $fromStatus = $reservation->status;

        if ($fromStatus === null) {
            throw ValidationException::withMessages([
                'status' => [__('reservations.invalid_status_transition')],
            ]);
        }

        $this->transitionService->assertCanTransition($fromStatus, $toStatus);

        return DB::transaction(function () use ($reservation, $fromStatus, $toStatus, $actor, $source, $reason): Reservation {
            $updates = ['status' => $toStatus];

            if ($toStatus === ReservationStatus::Completed) {
                $updates['completed_at'] = now();
            }

            if ($toStatus === ReservationStatus::Confirmed) {
                $updates['confirmed_at'] = now();
            }

            if ($toStatus === ReservationStatus::CheckedIn) {
                $updates['checked_in_at'] = now();
            }

            if ($toStatus === ReservationStatus::NoShow) {
                $updates['no_show_at'] = now();
            }

            if ($toStatus === ReservationStatus::Rejected) {
                $updates['rejection_reason'] = $reason;
            }

            $reservation->update($updates);

            $this->recordEvent->execute(
                reservation: $reservation,
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                actor: $actor,
                source: $source,
                reason: $reason,
            );

            DB::afterCommit(function () use ($reservation, $fromStatus, $toStatus, $actor): void {
                ReservationStatusChanged::dispatch(
                    $reservation->fresh(),
                    $fromStatus,
                    $toStatus,
                    $actor,
                );
            });

            return $reservation->fresh(['business', 'resource.group', 'customer']);
        });
    }
}
