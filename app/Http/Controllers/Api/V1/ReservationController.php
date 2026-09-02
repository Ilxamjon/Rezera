<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reservations\CreateReservationAction;
use App\Http\Requests\Api\V1\Reservation\StoreReservationRequest;
use App\Http\Resources\Api\V1\ReservationResource;
use App\Models\Business;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReservationController extends BaseApiController
{
    public function store(
        StoreReservationRequest $request,
        Business $business,
        CreateReservationAction $createReservation,
    ): JsonResponse {
        Gate::authorize('create', [Reservation::class, $business]);

        $reservation = $createReservation->execute(
            customer: $request->user(),
            business: $business,
            data: $request->validated(),
            idempotencyKey: $request->idempotencyKey(),
        );

        return $this->created(
            new ReservationResource($reservation),
            __('reservations.created'),
        );
    }
}
