<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Reservations\CheckInReservationAction;
use App\Actions\Reservations\CheckOutReservationAction;
use App\Domain\Reservations\Enums\CheckInMethod;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Reservation\QrCheckInRequest;
use App\Http\Resources\Api\V1\ReservationManagementResource;
use App\Http\Resources\Api\V1\ReservationSessionResource;
use App\Models\Business;
use App\Models\Reservation;
use App\Services\Reservations\BusinessOperationsService;
use App\Services\Reservations\QrTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReservationCheckInController extends BaseApiController
{
    public function checkIn(
        Request $request,
        Business $business,
        Reservation $reservation,
        CheckInReservationAction $checkIn,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $reservation);
        Gate::authorize('checkIn', $reservation);

        $updated = $checkIn->execute(
            reservation: $reservation,
            actor: $request->user(),
            method: CheckInMethod::Staff,
            source: ReservationEventSource::ApiOwner,
            request: $request,
        );

        return $this->success(new ReservationManagementResource($updated), __('reservations.checked_in'));
    }

    public function checkInQr(
        QrCheckInRequest $request,
        Business $business,
        Reservation $reservation,
        CheckInReservationAction $checkIn,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $reservation);
        Gate::authorize('checkIn', $reservation);

        $updated = $checkIn->execute(
            reservation: $reservation,
            actor: $request->user(),
            method: CheckInMethod::Qr,
            source: ReservationEventSource::ApiOwner,
            qrToken: $request->input('qr_token'),
            request: $request,
        );

        return $this->success(new ReservationManagementResource($updated), __('reservations.checked_in'));
    }

    public function checkOut(
        Request $request,
        Business $business,
        Reservation $reservation,
        CheckOutReservationAction $checkOut,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $reservation);
        Gate::authorize('checkOut', $reservation);

        $updated = $checkOut->execute(
            reservation: $reservation,
            actor: $request->user(),
            method: CheckInMethod::Staff,
            source: ReservationEventSource::ApiOwner,
            request: $request,
        );

        return $this->success(new ReservationManagementResource($updated), __('reservations.checked_out'));
    }

    public function todayOperations(
        Request $request,
        Business $business,
        BusinessOperationsService $operations,
    ): JsonResponse {
        Gate::authorize('viewBusiness', [Reservation::class, $business]);

        $data = $operations->today($business, $request->filled('resource_id') ? $request->string('resource_id')->toString() : null);

        return $this->success([
            'date' => $data['date'],
            'timezone' => $data['timezone'],
            'counts' => [
                'reservations_today' => $data['reservations_today'],
                'upcoming' => $data['upcoming_count'],
                'active_sessions' => $data['active_sessions_count'],
                'overdue' => $data['overdue_count'],
            ],
            'reservations' => ReservationManagementResource::collection($data['reservations']),
            'active_sessions' => ReservationSessionResource::collection($data['active_sessions']),
            'upcoming' => ReservationManagementResource::collection($data['upcoming']),
            'overdue' => ReservationManagementResource::collection($data['overdue']),
        ]);
    }

    public function activeSessions(
        Request $request,
        Business $business,
        BusinessOperationsService $operations,
    ): JsonResponse {
        Gate::authorize('viewBusiness', [Reservation::class, $business]);

        $sessions = $operations->activeSessions(
            $business,
            $request->filled('resource_id') ? $request->string('resource_id')->toString() : null,
        );

        return $this->success(ReservationSessionResource::collection($sessions));
    }

    public function resourceOccupancy(
        Business $business,
        BusinessOperationsService $operations,
    ): JsonResponse {
        Gate::authorize('viewBusiness', [Reservation::class, $business]);

        $items = collect($operations->resourceOccupancy($business))->map(fn (array $item): array => [
            'resource' => [
                'id' => $item['resource']->id,
                'name' => $item['resource']->name,
                'code' => $item['resource']->code,
                'status' => $item['resource']->status?->value,
                'category' => $item['resource']->group?->name,
            ],
            'occupancy_state' => $item['occupancy_state'],
            'checked_in_at' => $item['checked_in_at'],
            'scheduled_end_at' => $item['scheduled_end_at'],
            'is_overtime' => $item['is_overtime'],
            'customer' => $item['customer'] ? [
                'id' => $item['customer']->id,
                'name' => $item['customer']->name,
            ] : null,
            'reservation' => $item['current_reservation'] ? [
                'id' => $item['current_reservation']->id,
                'reservation_number' => $item['current_reservation']->reservation_number,
            ] : null,
        ]);

        return $this->success($items);
    }

    private function ensureBelongsToBusiness(Business $business, Reservation $reservation): void
    {
        if ($reservation->business_id !== $business->id) {
            abort(404);
        }
    }
}
