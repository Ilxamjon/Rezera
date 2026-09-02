<?php

namespace App\Services\Reviews;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reviews\Enums\ReviewStatus;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ReviewEligibilityService
{
    public function canCreate(User $user, Business $business, Reservation $reservation): bool
    {
        return $this->denialReason($user, $business, $reservation) === null;
    }

    public function assertCanCreate(User $user, Business $business, Reservation $reservation): void
    {
        $reason = $this->denialReason($user, $business, $reservation);

        if ($reason !== null) {
            throw ValidationException::withMessages([
                'reservation_id' => [__($reason)],
            ]);
        }
    }

    public function denialReason(User $user, Business $business, Reservation $reservation): ?string
    {
        if ($reservation->customer_id !== $user->id) {
            return 'reviews.reservation_not_owned';
        }

        if ($reservation->business_id !== $business->id) {
            return 'reviews.reservation_business_mismatch';
        }

        if ($reservation->status !== ReservationStatus::Completed) {
            return 'reviews.reservation_not_completed';
        }

        if (Review::withTrashed()->where('reservation_id', $reservation->id)->exists()) {
            return 'reviews.reservation_already_reviewed';
        }

        return null;
    }

    public function isPubliclyVisible(Review $review): bool
    {
        return $review->deleted_at === null
            && $review->status?->isPubliclyVisible() === true;
    }
}
