<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Reviews\CreateReviewAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Review\StoreReservationReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Reservation;
use App\Services\Reviews\ReviewEligibilityService;
use Illuminate\Http\JsonResponse;

class ReservationReviewController extends BaseApiController
{
    public function store(
        StoreReservationReviewRequest $request,
        Reservation $reservation,
        CreateReviewAction $createReview,
        ReviewEligibilityService $eligibility,
    ): JsonResponse {
        $business = $reservation->business;

        if ($business === null) {
            abort(404);
        }

        $eligibility->assertCanCreate($request->user(), $business, $reservation);

        $review = $createReview->execute(
            $request->user(),
            $business,
            $reservation,
            $request->reviewData(),
        );

        $review->load(['user:id,name,avatar_url']);

        return $this->created(new ReviewResource($review), __('reviews.created'));
    }
}
