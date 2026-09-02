<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Availability\CheckAvailabilityRequest;
use App\Http\Resources\Api\V1\AvailabilityResponseResource;
use App\Models\Business;
use App\Models\Resource;
use App\Services\Availability\AvailabilityEngine;
use App\Services\Availability\AvailabilityQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AvailabilityController extends BaseApiController
{
    public function index(
        CheckAvailabilityRequest $request,
        Business $business,
        AvailabilityEngine $availabilityEngine,
    ): JsonResponse {
        Gate::authorize('viewPublic', $business);

        return $this->respond($request, $business, $availabilityEngine, managementView: false);
    }

    public function show(
        CheckAvailabilityRequest $request,
        Business $business,
        Resource $resource,
        AvailabilityEngine $availabilityEngine,
    ): JsonResponse {
        Gate::authorize('viewPublic', $business);
        $this->ensureResourceBelongsToBusiness($business, $resource);

        $request->merge(['resource_id' => $resource->id]);

        return $this->respond($request, $business, $availabilityEngine, managementView: false);
    }

    private function respond(
        CheckAvailabilityRequest $request,
        Business $business,
        AvailabilityEngine $availabilityEngine,
        bool $managementView,
    ): JsonResponse {
        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');

        $result = $availabilityEngine->calculate(new AvailabilityQuery(
            business: $business,
            date: CarbonImmutable::parse($request->string('date')->toString(), $timezone)->startOfDay(),
            startTime: $request->string('start_time')->toString(),
            endTime: $request->string('end_time')->toString(),
            resourceCategoryId: $request->resolvedCategoryId(),
            resourceId: $request->resolvedResourceId(),
            includeUnavailable: $request->includeUnavailable(),
            managementView: $managementView,
        ));

        $request->attributes->set('availability_management_view', $managementView);

        return $this->success(new AvailabilityResponseResource($result));
    }

    private function ensureResourceBelongsToBusiness(Business $business, Resource $resource): void
    {
        if ($resource->business_id !== $business->id) {
            abort(404);
        }
    }
}
