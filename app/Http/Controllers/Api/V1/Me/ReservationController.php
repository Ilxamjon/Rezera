<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Reservations\CancelReservationAction;
use App\Domain\Reservations\Enums\CancelledByActorType;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Reservation\CancelReservationRequest;
use App\Http\Requests\Api\V1\Reservation\ListCustomerReservationsRequest;
use App\Http\Resources\Api\V1\ReservationResource;
use App\Models\Reservation;
use App\Services\Reservations\ReservationListFilters;
use App\Services\Reservations\ReservationQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReservationController extends BaseApiController
{
    public function index(
        ListCustomerReservationsRequest $request,
        ReservationQueryBuilder $queryBuilder,
    ): JsonResponse {
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);

        $query = $queryBuilder->forCustomer(
            $user->id,
            ReservationListFilters::fromRequest($request),
        );

        return $this->paginatedResource(
            $query->paginate($perPage),
            ReservationResource::class,
        );
    }

    public function upcoming(
        ListCustomerReservationsRequest $request,
        ReservationQueryBuilder $queryBuilder,
    ): JsonResponse {
        $request->merge(['scope' => 'upcoming']);

        return $this->index($request, $queryBuilder);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        Gate::authorize('view', $reservation);

        $reservation->load(['business', 'resource.group']);

        return $this->success(new ReservationResource($reservation));
    }

    public function cancel(
        CancelReservationRequest $request,
        Reservation $reservation,
        CancelReservationAction $cancelReservation,
    ): JsonResponse {
        Gate::authorize('cancel', $reservation);

        $cancelled = $cancelReservation->execute(
            reservation: $reservation,
            actor: $request->user(),
            actorType: CancelledByActorType::Customer,
            source: ReservationEventSource::ApiCustomer,
            reason: $request->validated('reason'),
        );

        return $this->success(
            new ReservationResource($cancelled),
            __('reservations.cancelled'),
        );
    }
}
