<?php

namespace App\Services\Reviews;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;

/**
 * @deprecated Use RatingSummaryService directly.
 */
final class ReviewRatingService
{
    public function __construct(
        private readonly RatingSummaryService $ratingSummary,
    ) {}

    /**
     * @param  Builder<Business>  $query
     */
    public function applyAggregates(Builder $query): void
    {
        $this->ratingSummary->applyAggregates($query);
    }

    /**
     * @return array{average: float|null, count: int, distribution: array<int, int>}
     */
    public function summaryForBusiness(Business $business): array
    {
        return $this->ratingSummary->summaryForBusiness($business);
    }
}
