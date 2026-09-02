<?php

namespace App\Actions\Reservations;

use App\Domain\Promotions\Enums\PromoRedemptionStatus;
use App\Domain\Reservations\Enums\CancelledByActorType;
use App\Models\PromoCodeRedemption;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Events\Reservations\ReservationStatusChanged;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Reservations\ReservationRulesEngine;
use App\Services\Reservations\ReservationTransitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CancelReservationAction
{
    public function __construct(
        private readonly ReservationTransitionService $transitionService,
        private readonly ReservationRulesEngine $rulesEngine,
        private readonly RecordReservationEventAction $recordEvent,
    ) {}

    public function execute(
        Reservation $reservation,
        User $actor,
        CancelledByActorType $actorType,
        ReservationEventSource $source,
        ?string $reason = null,
    ): Reservation {
        if ($reservation->status === ReservationStatus::Cancelled) {
            return $reservation->fresh(['business', 'resource.group', 'customer']);
        }

        if (! $this->transitionService->isCancellableByCustomer($reservation->status)
            && $actorType === CancelledByActorType::Customer) {
            throw ValidationException::withMessages([
                'reservation' => [__('reservations.cannot_cancel')],
            ]);
        }

        if ($actorType === CancelledByActorType::Customer) {
            $this->rulesEngine->assertCustomerCanCancel($reservation);
        } elseif ($actorType !== CancelledByActorType::System) {
            $this->rulesEngine->assertBusinessCanCancel($reservation);
        }

        $this->transitionService->assertCanTransition(
            $reservation->status,
            ReservationStatus::Cancelled,
        );

        return DB::transaction(function () use ($reservation, $actor, $actorType, $source, $reason): Reservation {
            $fromStatus = $reservation->status;

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $actor->id,
                'cancelled_by_actor_type' => $actorType,
            ]);

            PromoCodeRedemption::query()
                ->where('reservation_id', $reservation->id)
                ->where('status', PromoRedemptionStatus::Redeemed->value)
                ->update(['status' => PromoRedemptionStatus::Cancelled->value]);

            $this->recordEvent->execute(
                reservation: $reservation,
                fromStatus: $fromStatus,
                toStatus: ReservationStatus::Cancelled,
                actor: $actor,
                source: $source,
                reason: $reason,
            );

            DB::afterCommit(function () use ($reservation, $fromStatus, $actor): void {
                ReservationStatusChanged::dispatch(
                    $reservation->fresh(),
                    $fromStatus,
                    ReservationStatus::Cancelled,
                    $actor,
                );
            });

            return $reservation->fresh(['business', 'resource.group', 'customer']);
        });
    }
}
