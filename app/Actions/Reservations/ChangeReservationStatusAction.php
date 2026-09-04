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

        return DB::transaction(function () use ($reservation, $toStatus, $actor, $source, $reason): Reservation {
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $locked->status;

            if ($fromStatus === null) {
                throw ValidationException::withMessages([
                    'status' => [__('reservations.invalid_status_transition')],
                ]);
            }

            $this->transitionService->assertCanTransition($fromStatus, $toStatus);

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

            $locked->update($updates);

            $this->recordEvent->execute(
                reservation: $locked,
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                actor: $actor,
                source: $source,
                reason: $reason,
            );

            DB::afterCommit(function () use ($locked, $fromStatus, $toStatus, $actor): void {
                ReservationStatusChanged::dispatch(
                    $locked->fresh(),
                    $fromStatus,
                    $toStatus,
                    $actor,
                );
            });

            return $locked->fresh(['business', 'resource.group', 'customer']);
        });
    }
}
