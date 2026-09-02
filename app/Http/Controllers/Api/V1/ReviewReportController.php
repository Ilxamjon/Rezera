<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reviews\ReportReviewAction;
use App\Http\Requests\Api\V1\Review\StoreReviewReportRequest;
use App\Http\Resources\Api\V1\ReviewReportResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class ReviewReportController extends BaseApiController
{
    public function store(
        StoreReviewReportRequest $request,
        Review $review,
        ReportReviewAction $reportReview,
    ): JsonResponse {
        $report = $reportReview->execute(
            $request->user(),
            $review,
            $request->reason(),
            $request->description(),
        );

        return $this->created(new ReviewReportResource($report), __('reviews.reported'));
    }
}
