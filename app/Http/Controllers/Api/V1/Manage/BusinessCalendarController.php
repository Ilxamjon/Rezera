<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Calendar\BusinessCalendarDayRequest;
use App\Http\Requests\Api\V1\Calendar\BusinessCalendarRangeRequest;
use App\Http\Requests\Api\V1\Calendar\BusinessCalendarWeekRequest;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\Resource;
use App\Services\Calendar\BusinessCalendarService;
use App\Support\Calendar\CalendarDateRange;
use App\Support\Calendar\CalendarFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BusinessCalendarController extends BaseApiController
{
    public function day(
        BusinessCalendarDayRequest $request,
        Business $business,
        BusinessCalendarService $calendar,
    ): JsonResponse {
        Gate::authorize('viewBusiness', [Reservation::class, $business]);

        $date = $request->filled('date') ? $request->string('date')->toString() : null;

        return $this->success($calendar->day(
            $business,
            CalendarDateRange::forDay($business, $date),
            CalendarFilter::fromRequest($request),
        ));
    }

    public function week(
        BusinessCalendarWeekRequest $request,
        Business $business,
        BusinessCalendarService $calendar,
    ): JsonResponse {
        Gate::authorize('viewBusiness', [Reservation::class, $business]);

        $date = $request->filled('date') ? $request->string('date')->toString() : null;

        return $this->success($calendar->week(
            $business,
            CalendarDateRange::forWeek($business, $date),
            CalendarFilter::fromRequest($request),
        ));
    }

    public function timeline(
        BusinessCalendarRangeRequest $request,
        Business $business,
        BusinessCalendarService $calendar,
    ): JsonResponse {
        Gate::authorize('viewBusiness', [Reservation::class, $business]);

        return $this->success($calendar->timeline(
            $business,
            CalendarDateRange::fromRequest(
                $business,
                $request->filled('from') ? $request->string('from')->toString() : null,
                $request->filled('to') ? $request->string('to')->toString() : null,
            ),
            CalendarFilter::fromRequest($request),
        ));
    }

    public function resourceSchedule(
        BusinessCalendarRangeRequest $request,
        Business $business,
        Resource $resource,
        BusinessCalendarService $calendar,
    ): JsonResponse {
        $this->ensureBelongsToBusiness($business, $resource);
        Gate::authorize('viewBusiness', [Reservation::class, $business]);

        return $this->success($calendar->resourceSchedule(
            $business,
            $resource,
            CalendarDateRange::fromRequest(
                $business,
                $request->filled('from') ? $request->string('from')->toString() : null,
                $request->filled('to') ? $request->string('to')->toString() : null,
            ),
            CalendarFilter::fromRequest($request),
        ));
    }

    private function ensureBelongsToBusiness(Business $business, Resource $resource): void
    {
        if ($resource->business_id !== $business->id) {
            abort(404);
        }
    }
}
