<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Reviews\DeleteReviewAction;
use App\Actions\Reviews\UpdateReviewAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Review\UpdateReviewRequest;
use App\Http\Resources\Api\V1\CustomerReviewResource;
use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\ReviewQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReviewController extends BaseApiController
{
    public function index(Request $request, ReviewQueryBuilder $queryBuilder): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);

        $query = $queryBuilder->forUser($user->id);

        return $this->paginatedResource(
            $query->paginate($perPage),
            CustomerReviewResource::class,
        );
    }

    public function update(
        UpdateReviewRequest $request,
        Review $review,
        UpdateReviewAction $updateReview,
    ): JsonResponse {
        $updated = $updateReview->execute(
            $request->user(),
            $review,
            $request->reviewData(),
        );

        $updated->load(['business:id,name,cover_image_url,city']);

        return $this->success(new CustomerReviewResource($updated), __('reviews.updated'));
    }

    public function destroy(
        Request $request,
        Review $review,
        DeleteReviewAction $deleteReview,
    ): JsonResponse {
        Gate::authorize('delete', $review);

        $deleteReview->execute($request->user(), $review);

        return $this->success(null, __('reviews.deleted'));
    }
}
