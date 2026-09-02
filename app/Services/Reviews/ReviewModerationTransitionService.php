<?php

namespace App\Services\Reviews;

use App\Domain\Reviews\Enums\ReviewStatus;
use App\Models\Review;
use Illuminate\Validation\ValidationException;

final class ReviewModerationTransitionService
{
    /**
     * @return list<ReviewStatus>
     */
    public function allowedTransitions(ReviewStatus $from): array
    {
        return match ($from) {
            ReviewStatus::Pending => [ReviewStatus::Published, ReviewStatus::Rejected],
            ReviewStatus::Published => [ReviewStatus::Hidden, ReviewStatus::Rejected],
            ReviewStatus::Hidden => [ReviewStatus::Published],
            ReviewStatus::Rejected => [ReviewStatus::Published],
        };
    }

    public function assertCanTransition(Review $review, ReviewStatus $to): void
    {
        $from = $review->status ?? ReviewStatus::Pending;

        if (! in_array($to, $this->allowedTransitions($from), true)) {
            throw ValidationException::withMessages([
                'status' => [__('reviews.invalid_status_transition', [
                    'from' => $from->value,
                    'to' => $to->value,
                ])],
            ]);
        }
    }
}
