<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\ChangeReservationStatusAction;
use App\Domain\Reservations\Enums\CancelledByActorType;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Reservation\CancelReservationRequest;
use App\Http\Requests\Api\V1\Reservation\ChangeReservationStatusRequest;
use App\Http\Requests\Api\V1\Reservation\ListBusinessReservationsRequest;
use App\Http\Resources\Api\V1\ReservationManagementResource;
use App\Models\Business;
use App\Models\Reservation;
use App\Services\Authorization\BusinessAuthorizationService;
use App\Services\Reservations\ReservationListFilters;
use App\Services\Reservations\ReservationQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReservationController extends BaseApiController
{
    public function index(
        ListBusinessReservationsRequest $request,
        Business $business,
        ReservationQueryBuilder $queryBuilder,
    ): JsonResponse {
        Gate::authorize('viewBusiness', [Reservation::class, $business]);

        return $this->paginatedReservations(
            $request,
            $queryBuilder->forBusiness(
                $business,
                ReservationListFilters::fromRequest($request, $business->timezone),
            ),
        );
    }

    public function today(
        ListBusinessReservationsRequest $request,
        Business $business,
        ReservationQueryBuilder $queryBuilder,
    ): JsonResponse {
        Gate::authorize('viewBusiness', [Reservation::class, $business]);

        $request->merge(['scope' => 'today']);

        return $this->paginatedReservations(
            $request,
            $queryBuilder->forBusiness(
                $business,
                ReservationListFilters::fromRequest($request, $business->timezone),
            ),
        );
    }

    public function upcoming(
        ListBusinessReservationsRequest $request,
        Business $business,
        ReservationQueryBuilder $queryBuilder,
    ): JsonResponse {
        Gate::authorize('viewBusiness', [Reservation::class, $business]);

        $request->merge(['scope' => 'upcoming']);

        return $this->paginatedReservations(
            $request,
            $queryBuilder->forBusiness(
                $business,
                ReservationListFilters::fromRequest($request, $business->timezone),
            ),
        );
    }

    public function show(Business $business, Reservation $reservation): JsonResponse
    {
        $this->ensureBelongsToBusiness($business, $reservation);
        Gate::authorize('view', $reservation);

        $reservation->load(['resource.group', 'customer']);

        return $this->success(new ReservationManagementResource($reservation));
    }

    public function updateStatus(
        ChangeReservationStatusRequest $request,
        Business $business,
        Reservation $reservation,
        ChangeReservationStatusAction $changeStatus,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $reservation);

        $status = ReservationStatus::from($request->validated('status'));

        $updated = $changeStatus->execute(
            reservation: $reservation,
            toStatus: $status,
            actor: $request->user(),
            source: ReservationEventSource::ApiOwner,
            reason: $request->validated('reason'),
        );

        return $this->success(
            new ReservationManagementResource($updated),
            __('reservations.status_updated'),
        );
    }

    public function cancel(
        CancelReservationRequest $request,
        Business $business,
        Reservation $reservation,
        CancelReservationAction $cancelReservation,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $reservation);
        Gate::authorize('cancel', $reservation);

        $membership = app(BusinessAuthorizationService::class)
            ->membership($request->user(), $business->id);

        $actorType = $membership?->member_role === \App\Domain\Businesses\Enums\BusinessMemberRole::Owner
            ? CancelledByActorType::Owner
            : CancelledByActorType::Staff;

        $cancelled = $cancelReservation->execute(
            reservation: $reservation,
            actor: $request->user(),
            actorType: $actorType,
            source: ReservationEventSource::ApiOwner,
            reason: $request->validated('reason'),
        );

        return $this->success(
            new ReservationManagementResource($cancelled),
            __('reservations.cancelled'),
        );
    }

    private function paginatedReservations(
        ListBusinessReservationsRequest $request,
        \Illuminate\Database\Eloquent\Builder $query,
    ): JsonResponse {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 50);

        return $this->paginatedResource(
            $query->paginate($perPage),
            ReservationManagementResource::class,
        );
    }

    private function ensureBelongsToBusiness(Business $business, Reservation $reservation): void
    {
        if ($reservation->business_id !== $business->id) {
            abort(404);
        }
    }
}
