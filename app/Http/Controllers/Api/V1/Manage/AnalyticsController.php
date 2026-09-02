<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Analytics\AnalyticsRequest;
use App\Models\Business;
use App\Policies\AnalyticsPolicy;
use App\Domain\Subscriptions\SubscriptionFeature;
use App\Services\Analytics\BusinessAnalyticsService;
use App\Services\Analytics\CheckInAnalyticsService;
use App\Services\Analytics\CustomerAnalyticsService;
use App\Services\Analytics\FavoriteAnalyticsService;
use App\Services\Analytics\LoyaltyAnalyticsService;
use App\Services\Analytics\PeakHoursAnalyticsService;
use App\Services\Analytics\PromotionAnalyticsService;
use App\Services\Analytics\ReferralAnalyticsService;
use App\Services\Analytics\ReservationAnalyticsService;
use App\Services\Analytics\ResourceAnalyticsService;
use App\Services\Analytics\RevenueAnalyticsService;
use App\Services\Analytics\ReviewAnalyticsService;
use App\Services\Subscriptions\BusinessEntitlementService;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends BaseApiController
{
    public function __construct(
        private readonly AnalyticsPolicy $analyticsPolicy,
        private readonly BusinessAnalyticsService $businessAnalytics,
        private readonly ReservationAnalyticsService $reservationAnalytics,
        private readonly RevenueAnalyticsService $revenueAnalytics,
        private readonly ResourceAnalyticsService $resourceAnalytics,
        private readonly CustomerAnalyticsService $customerAnalytics,
        private readonly PeakHoursAnalyticsService $peakHoursAnalytics,
        private readonly CheckInAnalyticsService $checkInAnalytics,
        private readonly LoyaltyAnalyticsService $loyaltyAnalytics,
        private readonly PromotionAnalyticsService $promotionAnalytics,
        private readonly ReferralAnalyticsService $referralAnalytics,
        private readonly ReviewAnalyticsService $reviewAnalytics,
        private readonly FavoriteAnalyticsService $favoriteAnalytics,
        private readonly BusinessEntitlementService $entitlements,
    ) {}

    public function overview(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range, $filter, $compareRange, $includeFinancial] = $this->context($request, $business);

        return $this->success($this->businessAnalytics->overview(
            $business,
            $range,
            $filter,
            $includeFinancial,
            $compareRange,
        ));
    }

    public function dashboard(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        $filter = AnalyticsFilter::fromRequest($request);
        $includeFinancial = $this->includeFinancial($request, $business);

        return $this->success($this->businessAnalytics->dashboard($business, $filter, $includeFinancial));
    }

    public function reservations(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            'metrics' => $this->reservationAnalytics->metrics($business, $range, $filter),
            'trends' => $this->reservationAnalytics->trends($business, $range, $filter),
        ]);
    }

    public function revenue(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeFinancial($request, $business);
        $this->assertAdvancedAnalytics($business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            'metrics' => $this->revenueAnalytics->metrics($business, $range, $filter),
            'trends' => $this->revenueAnalytics->trends($business, $range, $filter),
        ]);
    }

    public function resources(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            'summary' => $this->resourceAnalytics->summary($business, $range, $filter),
            'resources' => $this->resourceAnalytics->byResource($business, $range, $filter),
        ]);
    }

    public function resourceRanking(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            ...$this->resourceAnalytics->ranking($business, $range, $filter),
        ]);
    }

    public function resourceCategories(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            ...$this->resourceAnalytics->byCategory($business, $range, $filter),
        ]);
    }

    public function customers(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            'metrics' => $this->customerAnalytics->metrics($business, $range, $filter),
        ]);
    }

    public function topCustomers(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeFinancial($request, $business);
        $this->assertAdvancedAnalytics($business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            ...$this->customerAnalytics->topCustomers($business, $range, $filter),
        ]);
    }

    public function peakHours(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            ...$this->peakHoursAnalytics->peakHours($business, $range, $filter),
        ]);
    }

    public function weekdays(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            ...$this->peakHoursAnalytics->weekdays($business, $range, $filter),
        ]);
    }

    public function hourlyOccupancy(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            ...$this->peakHoursAnalytics->hourlyOccupancy($business, $range, $filter),
        ]);
    }

    public function checkIns(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            'metrics' => $this->checkInAnalytics->metrics($business, $range, $filter),
        ]);
    }

    public function loyalty(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeFinancial($request, $business);
        $this->assertAdvancedAnalytics($business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            'metrics' => $this->loyaltyAnalytics->metrics($business, $range, $filter),
        ]);
    }

    public function promotions(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeFinancial($request, $business);
        $this->assertAdvancedAnalytics($business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            'metrics' => $this->promotionAnalytics->metrics($business, $range, $filter),
        ]);
    }

    public function referrals(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);
        $this->assertAdvancedAnalytics($business);

        [$range, $filter] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            'metrics' => $this->referralAnalytics->metrics($business, $range, $filter),
        ]);
    }

    public function reviews(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            'metrics' => $this->reviewAnalytics->metrics($business, $range),
            'trends' => $this->reviewAnalytics->trends($business, $range),
        ]);
    }

    public function favorites(AnalyticsRequest $request, Business $business): JsonResponse
    {
        $this->authorizeOperational($request, $business);

        [$range] = $this->context($request, $business, false);

        return $this->success([
            'period' => $range->toPeriodArray(),
            'metrics' => $this->favoriteAnalytics->metrics($business, $range),
            'trends' => $this->favoriteAnalytics->trends($business, $range),
        ]);
    }

    /**
     * @return array{0: AnalyticsDateRange, 1: AnalyticsFilter, 2: ?AnalyticsDateRange, 3: bool}
     */
    private function context(AnalyticsRequest $request, Business $business, bool $withCompare = true): array
    {
        $validated = $request->validated();
        $range = AnalyticsDateRange::fromRequest(
            $business,
            $validated['preset'] ?? null,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        $compareRange = null;

        if ($withCompare) {
            if (! empty($validated['compare_from']) && ! empty($validated['compare_to'])) {
                $compareRange = AnalyticsDateRange::fromExplicit(
                    $business,
                    $validated['compare_from'],
                    $validated['compare_to'],
                );
            } elseif ($request->boolean('compare_previous', true)) {
                $compareRange = $range->previousPeriod();
            }
        }

        return [
            $range,
            AnalyticsFilter::fromRequest($request),
            $compareRange,
            $this->includeFinancial($request, $business),
        ];
    }

    private function authorizeOperational(Request $request, Business $business): void
    {
        if (! $this->analyticsPolicy->viewOperational($request->user(), $business)) {
            abort(403);
        }
    }

    private function authorizeFinancial(Request $request, Business $business): void
    {
        if (! $this->analyticsPolicy->viewFinancial($request->user(), $business)) {
            abort(403);
        }
    }

    private function includeFinancial(Request $request, Business $business): bool
    {
        return $this->analyticsPolicy->viewFinancial($request->user(), $business);
    }

    private function assertAdvancedAnalytics(Business $business): void
    {
        $this->entitlements->check($business, SubscriptionFeature::ANALYTICS_ADVANCED);
    }
}
