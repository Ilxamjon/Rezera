<?php

namespace App\Actions\Reviews;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Reviews\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\RatingSummaryService;
use App\Services\Reviews\ReviewModerationTransitionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ModerateReviewAction
{
    public function __construct(
        private readonly CreateAuditLogAction $auditLog,
        private readonly ReviewModerationTransitionService $transitions,
        private readonly RatingSummaryService $ratingSummary,
    ) {}

    public function updateStatus(
        User $actor,
        Review $review,
        ReviewStatus $toStatus,
        ?string $reason = null,
        ?Request $request = null,
    ): Review {
        if ($review->trashed()) {
            throw ValidationException::withMessages([
                'status' => [__('reviews.review_deleted')],
            ]);
        }

        $this->transitions->assertCanTransition($review, $toStatus);

        $oldStatus = $review->status?->value;

        $review = match ($toStatus) {
            ReviewStatus::Published => $this->publish($review),
            ReviewStatus::Hidden => $this->applyHidden($review, $actor, $reason),
            ReviewStatus::Rejected => $this->applyRejected($review, $actor, $reason),
            ReviewStatus::Pending => throw ValidationException::withMessages([
                'status' => [__('reviews.invalid_status_transition', ['from' => $oldStatus, 'to' => $toStatus->value])],
            ]),
        };

        $this->auditLog->execute(
            action: 'review.'.$toStatus->value,
            entityType: 'review',
            entityId: $review->id,
            actor: $actor,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $toStatus->value, 'reason' => $reason],
            request: $request,
        );

        $this->ratingSummary->syncBusinessCache($review->business);

        return $review->fresh();
    }

    public function hide(User $actor, Review $review, ?string $reason = null, ?Request $request = null): Review
    {
        return $this->updateStatus($actor, $review, ReviewStatus::Hidden, $reason, $request);
    }

    public function restore(User $actor, Review $review, ?Request $request = null): Review
    {
        if ($review->trashed()) {
            throw ValidationException::withMessages([
                'status' => [__('reviews.review_deleted')],
            ]);
        }

        $this->transitions->assertCanTransition($review, ReviewStatus::Published);

        $oldStatus = $review->status?->value;
        $review = $this->publish($review);

        $this->auditLog->execute(
            action: 'review.restored',
            entityType: 'review',
            entityId: $review->id,
            actor: $actor,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => ReviewStatus::Published->value],
            request: $request,
        );

        $this->ratingSummary->syncBusinessCache($review->business);

        return $review->fresh();
    }

    public function reject(User $actor, Review $review, ?string $reason = null, ?Request $request = null): Review
    {
        return $this->updateStatus($actor, $review, ReviewStatus::Rejected, $reason, $request);
    }

    public function delete(User $actor, Review $review, ?string $reason = null, ?Request $request = null): Review
    {
        if ($review->trashed()) {
            throw ValidationException::withMessages([
                'status' => [__('reviews.review_deleted')],
            ]);
        }

        $oldStatus = $review->status?->value;

        $review->delete();

        $this->auditLog->execute(
            action: 'review.deleted',
            entityType: 'review',
            entityId: $review->id,
            actor: $actor,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'deleted', 'reason' => $reason],
            request: $request,
        );

        $this->ratingSummary->syncBusinessCache($review->business);

        return $review;
    }

    private function publish(Review $review): Review
    {
        $review->fill([
            'status' => ReviewStatus::Published,
            'hidden_at' => null,
            'hidden_by' => null,
            'hidden_reason' => null,
            'published_at' => $review->published_at ?? now(),
        ]);
        $review->save();

        return $review;
    }

    private function applyHidden(Review $review, User $actor, ?string $reason): Review
    {
        $review->fill([
            'status' => ReviewStatus::Hidden,
            'hidden_at' => now(),
            'hidden_by' => $actor->id,
            'hidden_reason' => $reason,
        ]);
        $review->save();

        return $review;
    }

    private function applyRejected(Review $review, User $actor, ?string $reason): Review
    {
        $review->fill([
            'status' => ReviewStatus::Rejected,
            'hidden_at' => now(),
            'hidden_by' => $actor->id,
            'hidden_reason' => $reason,
        ]);
        $review->save();

        return $review;
    }
}
