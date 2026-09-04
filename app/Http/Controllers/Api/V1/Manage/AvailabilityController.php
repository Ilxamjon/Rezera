<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Availability\CheckAvailabilityRequest;
use App\Http\Resources\Api\V1\AvailabilityResponseResource;
use App\Models\Business;
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
        Gate::authorize('viewManagement', $business);

        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');

        $result = $availabilityEngine->calculate(new AvailabilityQuery(
            business: $business,
            date: CarbonImmutable::parse($request->string('date')->toString(), $timezone)->startOfDay(),
            startTime: $request->string('start_time')->toString(),
            endTime: $request->string('end_time')->toString(),
            resourceCategoryId: $request->resolvedCategoryId(),
            resourceId: $request->resolvedResourceId(),
            includeUnavailable: true,
            managementView: true,
        ));

        $request->attributes->set('availability_management_view', true);

        return $this->success(new AvailabilityResponseResource($result));
    }
}
