<?php

namespace App\Services\Analytics;

use App\Domain\Reviews\Enums\ReviewReportStatus;
use App\Domain\Reviews\Enums\ReviewStatus;
use App\Models\Business;
use App\Services\Reviews\RatingSummaryService;
use App\Support\Analytics\AnalyticsDateRange;
use Illuminate\Support\Facades\DB;

final class ReviewAnalyticsService
{
    public function __construct(
        private readonly RatingSummaryService $ratingSummary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function metrics(Business $business, AnalyticsDateRange $range): array
    {
        $overall = $this->ratingSummary->summaryForBusiness($business);

        $periodRow = DB::table('reviews')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(*) FILTER (WHERE business_response IS NOT NULL) as with_response')
            ->where('business_id', $business->id)
            ->where('status', ReviewStatus::Published->value)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$range->utcStart(), $range->utcEnd()])
            ->first();

        $periodCount = (int) ($periodRow->total ?? 0);
        $withResponse = (int) ($periodRow->with_response ?? 0);

        $reportedCount = DB::table('review_reports')
            ->join('reviews', 'reviews.id', '=', 'review_reports.review_id')
            ->where('reviews.business_id', $business->id)
            ->where('review_reports.status', ReviewReportStatus::Open->value)
            ->count();

        return [
            'average_rating' => $overall['average'],
            'total_reviews' => $overall['count'],
            'distribution' => $overall['distribution'],
            'reviews_in_period' => $periodCount,
            'response_rate_percent' => $periodCount > 0
                ? round(($withResponse / $periodCount) * 100, 1)
                : null,
            'open_reports' => $reportedCount,
        ];
    }

    /**
     * @return list<array{date: string, count: int}>
     */
    public function trends(Business $business, AnalyticsDateRange $range): array
    {
        $rows = DB::table('reviews')
            ->selectRaw('DATE(created_at) as review_date')
            ->selectRaw('COUNT(*) as total')
            ->where('business_id', $business->id)
            ->where('status', ReviewStatus::Published->value)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$range->utcStart(), $range->utcEnd()])
            ->groupBy('review_date')
            ->orderBy('review_date')
            ->get();

        return $rows->map(fn ($row): array => [
            'date' => (string) $row->review_date,
            'count' => (int) $row->total,
        ])->all();
    }
}
