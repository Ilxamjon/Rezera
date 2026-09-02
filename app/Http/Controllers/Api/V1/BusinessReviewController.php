<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reviews\CreateReviewAction;
use App\Http\Requests\Api\V1\Review\CreateReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Http\Resources\Api\V1\ReviewSummaryResource;
use App\Models\Business;
use App\Services\Reviews\RatingSummaryService;
use App\Services\Reviews\ReviewQueryBuilder;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessReviewController extends BaseApiController
{
    public function index(
        Request $request,
        Business $business,
        ReviewQueryBuilder $queryBuilder,
        RatingSummaryService $ratingSummary,
    ): JsonResponse {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);
        $rating = $request->filled('rating') ? (int) $request->integer('rating') : null;
        $sort = $request->filled('sort') ? $request->string('sort')->toString() : null;

        $query = $queryBuilder->forBusiness($business, publicOnly: true);
        $queryBuilder->applyFilters($query, $rating);
        $queryBuilder->applySort($query, $sort);

        $paginator = $query->paginate($perPage);
        $summary = $ratingSummary->summaryForBusiness($business);

        return ApiResponse::success([
            'items' => ReviewResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'rating' => (new ReviewSummaryResource($summary))->resolve(),
        ]);
    }

    public function store(
        CreateReviewRequest $request,
        Business $business,
        CreateReviewAction $createReview,
    ): JsonResponse {
        $review = $createReview->execute(
            $request->user(),
            $business,
            $request->reservation(),
            $request->reviewData(),
        );

        $review->load(['user:id,name,avatar_url']);

        return $this->created(new ReviewResource($review), __('reviews.created'));
    }
}
