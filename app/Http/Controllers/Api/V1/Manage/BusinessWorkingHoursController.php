<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Businesses\UpdateBusinessWorkingHoursAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Business\UpdateBusinessWorkingHoursRequest;
use App\Http\Resources\Api\V1\BusinessWorkingHoursResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BusinessWorkingHoursController extends BaseApiController
{
    public function show(Business $business): JsonResponse
    {
        Gate::authorize('viewManagement', $business);

        $hours = $business->hours()->orderBy('weekday')->get();

        return $this->success(BusinessWorkingHoursResource::collection($hours));
    }

    public function update(
        UpdateBusinessWorkingHoursRequest $request,
        Business $business,
        UpdateBusinessWorkingHoursAction $updateWorkingHours,
    ): JsonResponse {
        $payload = collect($request->validated('working_hours'))
            ->map(static fn (array $hour): array => [
                'weekday' => (int) $hour['weekday'],
                'is_closed' => (bool) $hour['is_closed'],
                'is_open_24h' => (bool) $hour['is_open_24h'],
                'opens_at' => $hour['opens_at'] ?? null,
                'closes_at' => $hour['closes_at'] ?? null,
            ])
            ->all();

        $hours = $updateWorkingHours->execute($business, $payload);

        return $this->success(
            BusinessWorkingHoursResource::collection($hours),
            __('business.working_hours_updated'),
        );
    }
}
