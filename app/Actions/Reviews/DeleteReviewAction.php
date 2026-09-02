<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\RatingSummaryService;

final class DeleteReviewAction
{
    public function __construct(
        private readonly RatingSummaryService $ratingSummary,
    ) {}

    public function execute(User $user, Review $review): void
    {
        $business = $review->business;
        $review->delete();
        $this->ratingSummary->syncBusinessCache($business);
    }
}
