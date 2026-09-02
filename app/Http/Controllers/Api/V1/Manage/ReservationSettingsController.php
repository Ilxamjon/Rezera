<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Reservations\UpdateReservationSettingsAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Reservation\UpdateReservationSettingsRequest;
use App\Http\Resources\Api\V1\ReservationSettingsResource;
use App\Models\Business;
use App\Services\Reservations\BusinessSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReservationSettingsController extends BaseApiController
{
    public function show(Business $business, BusinessSettingsService $settingsService): JsonResponse
    {
        Gate::authorize('viewManagement', $business);

        $policy = $settingsService->getForBusiness($business);

        return $this->success(new ReservationSettingsResource($policy));
    }

    public function update(
        UpdateReservationSettingsRequest $request,
        Business $business,
        UpdateReservationSettingsAction $updateSettings,
    ): JsonResponse {
        Gate::authorize('manageReservationSettings', $business);

        $policy = $updateSettings->execute(
            business: $business,
            attributes: $request->validated(),
            actor: $request->user(),
            request: $request,
        );

        return $this->success(
            new ReservationSettingsResource($policy),
            __('reservation_rules.updated'),
        );
    }

    public function reset(
        Business $business,
        BusinessSettingsService $settingsService,
        UpdateReservationSettingsAction $updateSettings,
    ): JsonResponse {
        Gate::authorize('manageReservationSettings', $business);

        $policy = $updateSettings->execute(
            business: $business,
            attributes: $settingsService->defaults(),
            actor: request()->user(),
            request: request(),
        );

        return $this->success(
            new ReservationSettingsResource($policy),
            __('reservation_rules.reset'),
        );
    }
}
