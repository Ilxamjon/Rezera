<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Reservations\ChangeReservationStatusAction;
use App\Domain\Platform\Enums\PlatformPermission;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Http\Requests\Api\V1\Reservation\ChangeReservationStatusRequest;
use App\Http\Resources\Api\V1\ReservationManagementResource;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends AdminBaseController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::ReservationsView);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = Reservation::query()->with(['business', 'resource', 'customer']);

        if ($request->filled('search')) {
            $term = '%'.addcslashes($request->string('search')->toString(), '%_\\').'%';
            $query->where(function ($q) use ($term): void {
                $q->where('reservation_number', 'ilike', $term)
                    ->orWhere('customer_name_snapshot', 'ilike', $term)
                    ->orWhere('customer_phone_snapshot', 'ilike', $term)
                    ->orWhereHas('business', fn ($b) => $b->where('name', 'ilike', $term));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->string('business_id'));
        }

        $sort = $request->string('sort')->toString() === 'start_at' ? 'start_at' : 'created_at';
        $query->orderBy($sort, 'desc');

        return $this->paginatedResource($query->paginate($perPage), ReservationManagementResource::class);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::ReservationsView);
        $reservation->load(['business', 'resource.group', 'customer']);

        return $this->success(new ReservationManagementResource($reservation));
    }

    public function updateStatus(
        ChangeReservationStatusRequest $request,
        Reservation $reservation,
        ChangeReservationStatusAction $changeStatus,
    ): JsonResponse {
        $this->authorizePlatform(PlatformPermission::ReservationsManage);

        $updated = $changeStatus->execute(
            reservation: $reservation,
            toStatus: ReservationStatus::from($request->validated('status')),
            actor: $request->user(),
            source: ReservationEventSource::Admin,
            reason: $request->validated('reason'),
        );

        return $this->success(new ReservationManagementResource($updated));
    }
}
