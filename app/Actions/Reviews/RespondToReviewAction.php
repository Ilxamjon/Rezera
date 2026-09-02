<?php

namespace App\Actions\Reviews;

use App\Events\Reviews\ReviewResponsePublished;
use App\Models\Review;
use App\Models\User;

final class RespondToReviewAction
{
    public function execute(User $responder, Review $review, string $body): Review
    {
        $review->fill([
            'business_response' => $body,
            'business_responded_by' => $responder->id,
            'business_responded_at' => now(),
        ]);

        $review->save();

        $fresh = $review->fresh(['business', 'user', 'businessResponder']);

        ReviewResponsePublished::dispatch($fresh);

        return $fresh;
    }

    public function deleteResponse(Review $review): Review
    {
        $review->fill([
            'business_response' => null,
            'business_responded_by' => null,
            'business_responded_at' => null,
        ]);

        $review->save();

        return $review->fresh(['business', 'user']);
    }
}
