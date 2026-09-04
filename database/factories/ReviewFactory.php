<?php

namespace Database\Factories;

use App\Domain\Reviews\Enums\ReviewStatus;
use App\Models\Reservation;
use App\Models\Review;
use App\Services\Reviews\RatingSummaryService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Review $review): void {
            if ($review->reservation_id !== null) {
                $reservation = Reservation::query()->find($review->reservation_id);

                if ($reservation !== null) {
                    $review->business_id ??= $reservation->business_id;
                    $review->user_id ??= $reservation->customer_id;
                    $review->resource_id ??= $reservation->resource_id;
                }
            }
        })->afterCreating(function (Review $review): void {
            if ($review->status === ReviewStatus::Published && $review->business_id !== null) {
                app(RatingSummaryService::class)->syncBusinessCache($review->business);
            }
        });
    }

    public function definition(): array
    {
        $reservation = Reservation::factory()->create([
            'status' => \App\Domain\Reservations\Enums\ReservationStatus::Completed,
        ]);

        return [
            'business_id' => $reservation->business_id,
            'user_id' => $reservation->customer_id,
            'reservation_id' => $reservation->id,
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->optional()->sentence(3),
            'body' => fake()->optional()->paragraph(),
            'status' => ReviewStatus::Published,
            'published_at' => now(),
        ];
    }
}
