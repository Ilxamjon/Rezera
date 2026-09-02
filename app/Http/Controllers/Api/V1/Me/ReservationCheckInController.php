<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Reservations\CheckInReservationAction;
use App\Actions\Reservations\CheckOutReservationAction;
use App\Domain\Reservations\Enums\CheckInMethod;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Reservation\QrCheckInRequest;
use App\Http\Resources\Api\V1\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReservationCheckInController extends BaseApiController
{
    public function checkIn(
        Request $request,
        Reservation $reservation,
        CheckInReservationAction $checkIn,
    ): JsonResponse {
        Gate::authorize('checkIn', $reservation);

        if ($reservation->customer_id !== $request->user()->id) {
            abort(403);
        }

        $updated = $checkIn->execute(
            reservation: $reservation,
            actor: $request->user(),
            method: CheckInMethod::Customer,
            source: ReservationEventSource::ApiCustomer,
            request: $request,
        );

        return $this->success(new ReservationResource($updated), __('reservations.checked_in'));
    }

    public function checkInQr(
        QrCheckInRequest $request,
        Reservation $reservation,
        CheckInReservationAction $checkIn,
    ): JsonResponse {
        Gate::authorize('checkIn', $reservation);

        if ($reservation->customer_id !== $request->user()->id) {
            abort(403);
        }

        $updated = $checkIn->execute(
            reservation: $reservation,
            actor: $request->user(),
            method: CheckInMethod::Qr,
            source: ReservationEventSource::ApiCustomer,
            qrToken: $request->input('qr_token'),
            request: $request,
        );

        return $this->success(new ReservationResource($updated), __('reservations.checked_in'));
    }

    public function checkOut(
        Request $request,
        Reservation $reservation,
        CheckOutReservationAction $checkOut,
    ): JsonResponse {
        Gate::authorize('checkOut', $reservation);

        if ($reservation->customer_id !== $request->user()->id) {
            abort(403);
        }

        $updated = $checkOut->execute(
            reservation: $reservation,
            actor: $request->user(),
            method: CheckInMethod::Customer,
            source: ReservationEventSource::ApiCustomer,
            request: $request,
        );

        return $this->success(new ReservationResource($updated), __('reservations.checked_out'));
    }
}
