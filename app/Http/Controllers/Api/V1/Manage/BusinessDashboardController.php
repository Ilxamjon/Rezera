<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\BusinessDashboardResource;
use App\Models\Business;
use App\Policies\AnalyticsPolicy;
use App\Services\Reservations\BusinessDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessDashboardController extends BaseApiController
{
    public function __construct(
        private readonly AnalyticsPolicy $analyticsPolicy,
    ) {}

    public function show(Request $request, Business $business, BusinessDashboardService $dashboard): JsonResponse
    {
        if (! $this->analyticsPolicy->viewOperational($request->user(), $business)) {
            abort(403);
        }

        $includeFinancial = $this->analyticsPolicy->viewFinancial($request->user(), $business);
        $upcomingLimit = $request->filled('upcoming_limit')
            ? (int) $request->integer('upcoming_limit')
            : null;

        return $this->success(new BusinessDashboardResource(
            $dashboard->summary($business, $includeFinancial, $upcomingLimit),
        ));
    }
}
