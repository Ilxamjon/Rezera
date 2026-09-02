<?php

namespace App\Actions\Reviews;

use App\Domain\Reviews\Enums\ReviewStatus;
use App\Events\Reviews\ReviewCreated;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use App\Services\Reviews\RatingSummaryService;
use App\Services\Reviews\ReviewEligibilityService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateReviewAction
{
    public function __construct(
        private readonly ReviewEligibilityService $eligibility,
        private readonly RatingSummaryService $ratingSummary,
    ) {}

    /**
     * @param  array{rating: int, title?: string|null, body?: string|null}  $data
     */
    public function execute(User $user, Business $business, Reservation $reservation, array $data): Review
    {
        $this->eligibility->assertCanCreate($user, $business, $reservation);

        try {
            $review = DB::transaction(function () use ($user, $business, $reservation, $data): Review {
                return Review::query()->create([
                    'business_id' => $business->id,
                    'user_id' => $user->id,
                    'reservation_id' => $reservation->id,
                    'resource_id' => $reservation->resource_id,
                    'rating' => $data['rating'],
                    'title' => $data['title'] ?? null,
                    'body' => $data['body'] ?? null,
                    'status' => ReviewStatus::Published,
                    'published_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException|QueryException $exception) {
            if ($this->isDuplicateReviewException($exception)) {
                throw ValidationException::withMessages([
                    'reservation_id' => [__('reviews.reservation_already_reviewed')],
                ]);
            }

            throw $exception;
        }

        $this->ratingSummary->syncBusinessCache($business);

        ReviewCreated::dispatch($review->fresh(['business', 'user']));

        return $review;
    }

    private function isDuplicateReviewException(QueryException|\Throwable $exception): bool
    {
        return str_contains($exception->getMessage(), 'reviews_reservation_id_unique')
            || ($exception->errorInfo[0] ?? null) === '23505';
    }
}
