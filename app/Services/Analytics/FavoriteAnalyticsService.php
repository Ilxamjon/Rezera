<?php

namespace App\Services\Analytics;

use App\Models\Business;
use App\Models\BusinessFavorite;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsMetrics;
use Illuminate\Support\Facades\DB;

final class FavoriteAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(Business $business, AnalyticsDateRange $range): array
    {
        $total = BusinessFavorite::query()
            ->where('business_id', $business->id)
            ->count();

        $addedInPeriod = BusinessFavorite::query()
            ->where('business_id', $business->id)
            ->whereBetween('created_at', [$range->utcStart(), $range->utcEnd()])
            ->count();

        return [
            'total_favorites' => $total,
            'favorites_added' => $addedInPeriod,
        ];
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function trends(Business $business, AnalyticsDateRange $range): array
    {
        $rows = DB::table('business_favorites')
            ->selectRaw('DATE(created_at) as favorite_date')
            ->selectRaw('COUNT(*) as total')
            ->where('business_id', $business->id)
            ->whereBetween('created_at', [$range->utcStart(), $range->utcEnd()])
            ->groupBy('favorite_date')
            ->orderBy('favorite_date')
            ->get();

        return $rows->map(fn ($row): array => [
            'date' => (string) $row->favorite_date,
            'count' => (int) $row->total,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function compare(Business $business, AnalyticsDateRange $current, AnalyticsDateRange $previous): array
    {
        $currentAdded = BusinessFavorite::query()
            ->where('business_id', $business->id)
            ->whereBetween('created_at', [$current->utcStart(), $current->utcEnd()])
            ->count();

        $previousAdded = BusinessFavorite::query()
            ->where('business_id', $business->id)
            ->whereBetween('created_at', [$previous->utcStart(), $previous->utcEnd()])
            ->count();

        return AnalyticsMetrics::compare($currentAdded, $previousAdded);
    }
}
