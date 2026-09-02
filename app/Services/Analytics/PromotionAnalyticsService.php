<?php

namespace App\Services\Analytics;

use App\Models\Business;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\Reservation;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;
use App\Support\Analytics\AnalyticsMetrics;

final class PromotionAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $redemptions = PromoCodeRedemption::query()
            ->whereHas('promoCode', fn ($query) => $query->where('business_id', $business->id))
            ->whereBetween('redeemed_at', [$range->utcStart(), $range->utcEnd()]);

        $usageCount = (clone $redemptions)->count();
        $totalDiscount = (int) (clone $redemptions)->sum('discount_amount');

        $reservationsWithPromo = Reservation::query()
            ->where('business_id', $business->id)
            ->whereNotNull('promo_code_id')
            ->where('start_at', '<', $range->utcEnd())
            ->where('end_at', '>', $range->utcStart())
            ->count();

        $topPromos = PromoCodeRedemption::query()
            ->join('promo_codes', 'promo_codes.id', '=', 'promo_code_redemptions.promo_code_id')
            ->where('promo_codes.business_id', $business->id)
            ->whereBetween('promo_code_redemptions.redeemed_at', [$range->utcStart(), $range->utcEnd()])
            ->select('promo_codes.id', 'promo_codes.code', 'promo_codes.name')
            ->selectRaw('count(*) as usage_count')
            ->selectRaw('coalesce(sum(promo_code_redemptions.discount_amount), 0) as total_discount')
            ->groupBy('promo_codes.id', 'promo_codes.code', 'promo_codes.name')
            ->orderByDesc('usage_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'promo_code_id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'usage_count' => (int) $row->usage_count,
                'total_discount' => (int) $row->total_discount,
            ])
            ->values()
            ->all();

        return [
            'promo_usage_count' => $usageCount,
            'total_discount' => $totalDiscount,
            'reservations_with_promotions' => $reservationsWithPromo,
            'average_discount' => AnalyticsMetrics::average($totalDiscount, $usageCount),
            'most_used_promo_codes' => $topPromos,
            'active_promo_codes' => PromoCode::query()
                ->where('business_id', $business->id)
                ->where('is_active', true)
                ->count(),
        ];
    }
}
