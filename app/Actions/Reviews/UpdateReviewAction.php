<?php

namespace App\Actions\Reviews;

use App\Actions\Platform\CreateAuditLogAction;
use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\RatingSummaryService;

final class UpdateReviewAction
{
    public function __construct(
        private readonly CreateAuditLogAction $auditLog,
        private readonly RatingSummaryService $ratingSummary,
    ) {}

    /**
     * @param  array{rating?: int, title?: string|null, body?: string|null}  $data
     */
    public function execute(User $user, Review $review, array $data): Review
    {
        $oldValues = $review->only(['rating', 'title', 'body']);

        $review->fill([
            'rating' => $data['rating'] ?? $review->rating,
            'title' => array_key_exists('title', $data) ? $data['title'] : $review->title,
            'body' => array_key_exists('body', $data) ? $data['body'] : $review->body,
            'edited_at' => now(),
        ]);

        $review->save();

        $this->auditLog->execute(
            action: 'review.updated',
            entityType: 'review',
            entityId: $review->id,
            actor: $user,
            oldValues: $oldValues,
            newValues: $review->only(['rating', 'title', 'body']),
        );

        if (($oldValues['rating'] ?? null) !== $review->rating) {
            $this->ratingSummary->syncBusinessCache($review->business);
        }

        return $review->fresh(['business', 'user']);
    }
}
