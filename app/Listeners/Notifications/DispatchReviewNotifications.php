<?php

namespace App\Listeners\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use App\Events\Reviews\ReviewCreated;
use App\Events\Reviews\ReviewReported;
use App\Events\Reviews\ReviewResponsePublished;
use App\Services\Notifications\BusinessNotificationRecipientResolver;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class DispatchReviewNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly BusinessNotificationRecipientResolver $recipients,
    ) {}

    public function handleReviewCreated(ReviewCreated $event): void
    {
        $review = $event->review->loadMissing(['business', 'user']);
        $business = $review->business;

        if ($business === null) {
            return;
        }

        foreach ($this->recipients->bookingManagers($business->id) as $manager) {
            $this->notifications->notifyUser(
                user: $manager,
                type: NotificationType::ReviewCreated,
                entityKey: $review->id,
                placeholders: [
                    'business_name' => $business->name,
                    'rating' => (string) $review->rating,
                ],
                data: [
                    'review_id' => $review->id,
                    'business_id' => $business->id,
                    'rating' => $review->rating,
                ],
            );
        }
    }

    public function handleReviewResponsePublished(ReviewResponsePublished $event): void
    {
        $review = $event->review->loadMissing(['business', 'user']);
        $customer = $review->user;
        $business = $review->business;

        if ($customer === null || $business === null) {
            return;
        }

        $this->notifications->notifyUser(
            user: $customer,
            type: NotificationType::ReviewResponseReceived,
            entityKey: $review->id,
            placeholders: [
                'business_name' => $business->name,
            ],
            data: [
                'review_id' => $review->id,
                'business_id' => $business->id,
            ],
        );
    }

    public function handleReviewReported(ReviewReported $event): void
    {
        $report = $event->report->loadMissing(['review.business']);
        $review = $report->review;
        $business = $review?->business;

        if ($review === null || $business === null) {
            return;
        }

        foreach ($this->recipients->bookingManagers($business->id) as $manager) {
            $this->notifications->notifyUser(
                user: $manager,
                type: NotificationType::ReviewReported,
                entityKey: $report->id,
                placeholders: [
                    'business_name' => $business->name,
                ],
                data: [
                    'review_id' => $review->id,
                    'report_id' => $report->id,
                    'business_id' => $business->id,
                ],
            );
        }
    }
}
