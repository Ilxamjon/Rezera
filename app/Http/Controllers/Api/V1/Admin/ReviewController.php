<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Reviews\ModerateReviewAction;
use App\Domain\Platform\Enums\PlatformPermission;
use App\Domain\Reviews\Enums\ReviewStatus;
use App\Http\Requests\Api\V1\Review\ModerateReviewRequest;
use App\Http\Requests\Api\V1\Review\UpdateReviewModerationStatusRequest;
use App\Http\Resources\Api\V1\Admin\AdminReviewResource;
use App\Models\Review;
use App\Services\Reviews\ReviewQueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends AdminBaseController
{
    public function index(Request $request, ReviewQueryBuilder $queryBuilder): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::ReviewsView);

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $rating = $request->filled('rating') ? (int) $request->integer('rating') : null;
        $status = $request->filled('status') ? $request->string('status')->toString() : null;
        $search = $request->filled('search') ? $request->string('search')->toString() : null;
        $queue = $request->filled('queue') ? $request->string('queue')->toString() : null;

        $query = $queryBuilder->adminQuery();
        $queryBuilder->applyFilters($query, $rating, $status, $search);

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->string('business_id')->toString());
        }

        if ($queue !== null) {
            $queryBuilder->applyModerationQueue($query, $queue);
        }

        return $this->paginatedResource(
            $query->paginate($perPage),
            AdminReviewResource::class,
        );
    }

    public function show(Review $review): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::ReviewsView);

        $review->load(['business:id,name', 'user:id,name,avatar_url']);

        return $this->success(new AdminReviewResource($review));
    }

    public function updateStatus(
        UpdateReviewModerationStatusRequest $request,
        Review $review,
        ModerateReviewAction $moderateReview,
    ): JsonResponse {
        $this->authorizePlatform(PlatformPermission::ReviewsManage);

        $updated = match ($request->action()) {
            'publish' => $moderateReview->updateStatus($request->user(), $review, ReviewStatus::Published, $request->input('reason'), $request),
            'hide' => $moderateReview->hide($request->user(), $review, $request->input('reason'), $request),
            'reject' => $moderateReview->reject($request->user(), $review, $request->input('reason'), $request),
            'restore' => $moderateReview->restore($request->user(), $review, $request),
            'delete' => $moderateReview->delete($request->user(), $review, $request->input('reason'), $request),
            default => abort(422),
        };

        $updated->load(['business:id,name', 'user:id,name']);

        return $this->success(new AdminReviewResource($updated), __('reviews.moderation_updated'));
    }

    public function hide(ModerateReviewRequest $request, Review $review, ModerateReviewAction $moderateReview): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::ReviewsManage);

        $updated = $moderateReview->hide(
            $request->user(),
            $review,
            $request->input('reason'),
            $request,
        );

        $updated->load(['business:id,name', 'user:id,name']);

        return $this->success(new AdminReviewResource($updated), __('reviews.hidden'));
    }

    public function restore(Request $request, Review $review, ModerateReviewAction $moderateReview): JsonResponse
    {
        $this->authorizePlatform(PlatformPermission::ReviewsManage);

        $updated = $moderateReview->restore($request->user(), $review, $request);

        $updated->load(['business:id,name', 'user:id,name']);

        return $this->success(new AdminReviewResource($updated), __('reviews.restored'));
    }
}
