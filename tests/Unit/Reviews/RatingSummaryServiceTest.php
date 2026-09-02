<?php

namespace Tests\Unit\Reviews;

use App\Domain\Reviews\Enums\ReviewStatus;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\Review;
use App\Services\Reviews\RatingSummaryService;
use Tests\PostgresTestCase;

class RatingSummaryServiceTest extends PostgresTestCase
{
    public function test_distribution_sum_equals_count(): void
    {
        $business = Business::factory()->create();
        $ratings = [5, 5, 4, 3, 1];

        foreach ($ratings as $rating) {
            $reservation = Reservation::factory()->create([
                'business_id' => $business->id,
            ]);

            Review::factory()->create([
                'business_id' => $business->id,
                'user_id' => $reservation->customer_id,
                'reservation_id' => $reservation->id,
                'rating' => $rating,
                'status' => ReviewStatus::Published,
            ]);
        }

        $summary = app(RatingSummaryService::class)->summaryForBusiness($business);
        $distributionSum = array_sum($summary['distribution']);

        $this->assertSame(5, $summary['count']);
        $this->assertSame(5, $distributionSum);
        $this->assertSame(3.6, $summary['average']);
    }

    public function test_hidden_reviews_do_not_contribute(): void
    {
        $business = Business::factory()->create();

        $publishedReservation = Reservation::factory()->create(['business_id' => $business->id]);
        Review::factory()->create([
            'business_id' => $business->id,
            'user_id' => $publishedReservation->customer_id,
            'reservation_id' => $publishedReservation->id,
            'rating' => 5,
            'status' => ReviewStatus::Published,
        ]);

        $hiddenReservation = Reservation::factory()->create(['business_id' => $business->id]);
        Review::factory()->create([
            'business_id' => $business->id,
            'user_id' => $hiddenReservation->customer_id,
            'reservation_id' => $hiddenReservation->id,
            'rating' => 1,
            'status' => ReviewStatus::Hidden,
        ]);

        $summary = app(RatingSummaryService::class)->summaryForBusiness($business);

        $this->assertSame(1, $summary['count']);
        $this->assertSame(5.0, $summary['average']);
    }

    public function test_recalculate_syncs_business_cache(): void
    {
        $business = Business::factory()->create();
        $reservation = Reservation::factory()->create(['business_id' => $business->id]);

        Review::factory()->create([
            'business_id' => $business->id,
            'user_id' => $reservation->customer_id,
            'reservation_id' => $reservation->id,
            'rating' => 4,
            'status' => ReviewStatus::Published,
        ]);

        app(RatingSummaryService::class)->recalculate($business->id);
        $business->refresh();

        $this->assertSame(4.0, (float) $business->rating_average);
        $this->assertSame(1, $business->rating_count);
    }
}
