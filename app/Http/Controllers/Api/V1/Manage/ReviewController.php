<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Reviews\RespondToReviewAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Review\BusinessReviewResponseRequest;
use App\Http\Resources\Api\V1\BusinessReviewManagementResource;
use App\Models\Business;
use App\Models\Review;
use App\Services\Authorization\BusinessAuthorizationService;
use App\Services\Reviews\ReviewQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends BaseApiController
{
    public function __construct(
        private readonly BusinessAuthorizationService $authorization,
        private readonly ReviewQueryBuilder $queryBuilder,
    ) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $rating = $request->filled('rating') ? (int) $request->integer('rating') : null;
        $status = $request->filled('status') ? $request->string('status')->toString() : null;
        $search = $request->filled('search') ? $request->string('search')->toString() : null;
        $resourceId = $request->filled('resource_id') ? $request->string('resource_id')->toString() : null;
        $hasResponse = $request->has('has_response') ? $request->boolean('has_response') : null;
        $reported = $request->has('reported') ? $request->boolean('reported') : null;
        $dateFrom = $request->filled('date_from') ? $request->string('date_from')->toString() : null;
        $dateTo = $request->filled('date_to') ? $request->string('date_to')->toString() : null;

        $query = $this->queryBuilder->forBusiness($business, publicOnly: false);
        $this->queryBuilder->applyFilters(
            $query,
            $rating,
            $status,
            $search,
            $resourceId,
            $hasResponse,
            $reported,
            $dateFrom,
            $dateTo,
        );

        return $this->paginatedResource(
            $query->paginate($perPage),
            BusinessReviewManagementResource::class,
        );
    }

    public function show(Request $request, Business $business, Review $review): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $this->queryBuilder->assertBelongsToBusiness($review, $business);

        $this->authorize('view', $review);

        $review->load(['user:id,name,avatar_url', 'businessResponder:id,name']);

        return $this->success(new BusinessReviewManagementResource($review));
    }

    public function storeResponse(
        BusinessReviewResponseRequest $request,
        Business $business,
        Review $review,
        RespondToReviewAction $respondToReview,
    ): JsonResponse {
        $this->queryBuilder->assertBelongsToBusiness($review, $business);

        $updated = $respondToReview->execute(
            $request->user(),
            $review,
            $request->responseBody(),
        );

        $updated->load(['user:id,name,avatar_url', 'businessResponder:id,name']);

        return $this->success(new BusinessReviewManagementResource($updated), __('reviews.response_saved'));
    }

    public function updateResponse(
        BusinessReviewResponseRequest $request,
        Business $business,
        Review $review,
        RespondToReviewAction $respondToReview,
    ): JsonResponse {
        $this->queryBuilder->assertBelongsToBusiness($review, $business);

        $updated = $respondToReview->execute(
            $request->user(),
            $review,
            $request->responseBody(),
        );

        $updated->load(['user:id,name,avatar_url', 'businessResponder:id,name']);

        return $this->success(new BusinessReviewManagementResource($updated), __('reviews.response_saved'));
    }

    public function destroyResponse(
        Request $request,
        Business $business,
        Review $review,
        RespondToReviewAction $respondToReview,
    ): JsonResponse {
        $this->queryBuilder->assertBelongsToBusiness($review, $business);
        $this->authorize('respond', $review);

        $updated = $respondToReview->deleteResponse($review);
        $updated->load(['user:id,name,avatar_url']);

        return $this->success(new BusinessReviewManagementResource($updated), __('reviews.response_deleted'));
    }

    private function authorizeBusiness(Request $request, Business $business): void
    {
        $user = $request->user();

        if ($user === null || ! $this->authorization->canOperateBookings($user, $business->id)) {
            abort(403, __('auth.unauthorized'));
        }
    }
}
