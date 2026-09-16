<?php

namespace App\Actions\Reservations;

use App\Domain\Reservations\Enums\PaymentMethod;
use App\Domain\Reservations\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Exceptions\Payments\PaymentException;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarkReservationPaidAtVenueAction
{
    public function __construct(
        private readonly ChangeReservationStatusAction $changeReservationStatus,
    ) {}

    public function execute(Reservation $reservation, User $actor): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor): Reservation {
            /** @var Reservation $locked */
            $locked = Reservation::query()
                ->whereKey($reservation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->payment_status === PaymentStatus::Paid) {
                return $locked->load(['business', 'resource', 'customer']);
            }

            if (! in_array($locked->payment_status, [
                PaymentStatus::PayAtVenue,
                PaymentStatus::Unpaid,
            ], true)) {
                throw PaymentException::notAllowed(__('payments.reservation_not_payable'));
            }

            if (! in_array($locked->status, [
                ReservationStatus::Pending,
                ReservationStatus::Confirmed,
                ReservationStatus::CheckedIn,
                ReservationStatus::Completed,
            ], true)) {
                throw PaymentException::notAllowed(__('payments.reservation_not_payable'));
            }

            $locked->update([
                'payment_status' => PaymentStatus::Paid,
                'payment_method' => PaymentMethod::Venue,
            ]);

            if ($locked->status === ReservationStatus::Pending) {
                $this->changeReservationStatus->execute(
                    reservation: $locked->fresh(),
                    toStatus: ReservationStatus::Confirmed,
                    actor: $actor,
                    source: ReservationEventSource::StaffAction,
                    reason: __('payments.marked_paid_at_venue'),
                );
            }

            return $locked->fresh(['business', 'resource', 'customer']);
        });
    }
}
