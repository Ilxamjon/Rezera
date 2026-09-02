<?php

namespace Tests\Feature\Api\V1\Review;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reviews\Enums\ReviewStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class ReviewApiTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_customer_can_create_review_for_completed_reservation(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $reservation = Reservation::factory()->create([
            'customer_id' => $user->id,
            'business_id' => $business->id,
            'status' => ReservationStatus::Completed,
        ]);

        $response = $this->postJson('/api/v1/businesses/'.$business->id.'/reviews', [
            'reservation_id' => $reservation->id,
            'rating' => 5,
            'body' => 'Great experience',
        ], $this->authHeaders($user));

        $response
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.body', 'Great experience')
            ->assertJsonMissingPath('data.author.phone');

        $this->assertDatabaseHas('reviews', [
            'reservation_id' => $reservation->id,
            'user_id' => $user->id,
            'rating' => 5,
        ]);
    }

    public function test_cannot_review_non_completed_reservation(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $reservation = Reservation::factory()->create([
            'customer_id' => $user->id,
            'business_id' => $business->id,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->postJson('/api/v1/businesses/'.$business->id.'/reviews', [
            'reservation_id' => $reservation->id,
            'rating' => 4,
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_cannot_review_another_users_reservation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $reservation = Reservation::factory()->create([
            'customer_id' => $other->id,
            'business_id' => $business->id,
            'status' => ReservationStatus::Completed,
        ]);

        $this->postJson('/api/v1/businesses/'.$business->id.'/reviews', [
            'reservation_id' => $reservation->id,
            'rating' => 4,
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_duplicate_review_is_rejected(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $reservation = Reservation::factory()->create([
            'customer_id' => $user->id,
            'business_id' => $business->id,
            'status' => ReservationStatus::Completed,
        ]);

        $headers = $this->authHeaders($user);
        $payload = ['reservation_id' => $reservation->id, 'rating' => 5];

        $this->postJson('/api/v1/businesses/'.$business->id.'/reviews', $payload, $headers)->assertCreated();
        $this->postJson('/api/v1/businesses/'.$business->id.'/reviews', $payload, $headers)->assertStatus(422);
    }

    public function test_public_business_reviews_list_shows_only_published(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        $publishedReservation = Reservation::factory()->create([
            'business_id' => $business->id,
            'status' => ReservationStatus::Completed,
        ]);

        $hiddenReservation = Reservation::factory()->create([
            'business_id' => $business->id,
            'status' => ReservationStatus::Completed,
        ]);

        Review::factory()->create([
            'business_id' => $business->id,
            'user_id' => $publishedReservation->customer_id,
            'reservation_id' => $publishedReservation->id,
            'status' => ReviewStatus::Published,
            'rating' => 5,
        ]);

        Review::factory()->create([
            'business_id' => $business->id,
            'user_id' => $hiddenReservation->customer_id,
            'reservation_id' => $hiddenReservation->id,
            'status' => ReviewStatus::Hidden,
            'rating' => 1,
        ]);

        $this->getJson('/api/v1/businesses/'.$business->id.'/reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.rating', 5);
    }

    public function test_author_can_update_and_delete_review(): void
    {
        $user = User::factory()->create();
        $reservation = Reservation::factory()->create([
            'customer_id' => $user->id,
            'status' => ReservationStatus::Completed,
        ]);
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'reservation_id' => $reservation->id,
            'business_id' => $reservation->business_id,
            'rating' => 3,
        ]);

        $this->patchJson('/api/v1/me/reviews/'.$review->id, [
            'rating' => 4,
            'body' => 'Updated text',
        ], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.rating', 4);

        $this->deleteJson('/api/v1/me/reviews/'.$review->id, [], $this->authHeaders($user))
            ->assertOk();

        $this->assertSoftDeleted('reviews', ['id' => $review->id]);
    }

    public function test_non_author_cannot_update_review(): void
    {
        $review = Review::factory()->create();
        $other = User::factory()->create();

        $this->patchJson('/api/v1/me/reviews/'.$review->id, [
            'rating' => 1,
        ], $this->authHeaders($other))->assertForbidden();
    }

    public function test_business_manager_can_respond_to_review(): void
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'created_by_user_id' => $owner->id,
        ]);

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'member_role' => BusinessMemberRole::Owner,
        ]);

        $reservation = Reservation::factory()->create([
            'business_id' => $business->id,
            'status' => ReservationStatus::Completed,
        ]);

        $review = Review::factory()->create([
            'business_id' => $business->id,
            'user_id' => $reservation->customer_id,
            'reservation_id' => $reservation->id,
        ]);

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/reviews/'.$review->id.'/response', [
            'body' => 'Thank you!',
        ], $this->authHeaders($owner))
            ->assertOk()
            ->assertJsonPath('data.business_response.body', 'Thank you!');
    }

    public function test_other_business_cannot_respond_to_review(): void
    {
        $reservation = Reservation::factory()->create(['status' => ReservationStatus::Completed]);
        $review = Review::factory()->create([
            'business_id' => $reservation->business_id,
            'user_id' => $reservation->customer_id,
            'reservation_id' => $reservation->id,
        ]);
        $intruderBusiness = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $intruder = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $intruderBusiness->id,
            'user_id' => $intruder->id,
            'member_role' => BusinessMemberRole::Owner,
        ]);

        $this->postJson('/api/v1/manage/businesses/'.$intruderBusiness->id.'/reviews/'.$review->id.'/response', [
            'body' => 'Hack',
        ], $this->authHeaders($intruder))->assertNotFound();
    }

    public function test_admin_can_hide_and_restore_review(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
        $review = Review::factory()->create(['status' => ReviewStatus::Published]);

        $this->patchJson('/api/v1/admin/reviews/'.$review->id.'/hide', [
            'reason' => 'Spam',
        ], $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.status', ReviewStatus::Hidden->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'review.hidden',
            'entity_id' => $review->id,
        ]);

        $this->patchJson('/api/v1/admin/reviews/'.$review->id.'/restore', [], $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.status', ReviewStatus::Published->value);
    }

    public function test_hidden_reviews_excluded_from_rating_aggregate(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        $publishedReservation = Reservation::factory()->create([
            'business_id' => $business->id,
            'status' => ReservationStatus::Completed,
        ]);

        $hiddenReservation = Reservation::factory()->create([
            'business_id' => $business->id,
            'status' => ReservationStatus::Completed,
        ]);

        Review::factory()->create([
            'business_id' => $business->id,
            'user_id' => $publishedReservation->customer_id,
            'reservation_id' => $publishedReservation->id,
            'rating' => 5,
            'status' => ReviewStatus::Published,
        ]);

        Review::factory()->create([
            'business_id' => $business->id,
            'user_id' => $hiddenReservation->customer_id,
            'reservation_id' => $hiddenReservation->id,
            'rating' => 1,
            'status' => ReviewStatus::Hidden,
        ]);

        $this->getJson('/api/v1/businesses/'.$business->id)
            ->assertOk()
            ->assertJsonPath('data.rating.average', 5.0)
            ->assertJsonPath('data.rating.count', 1);
    }

    public function test_discovery_includes_rating_without_n_plus_one(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        foreach (range(1, 3) as $_) {
            $reservation = Reservation::factory()->create([
                'business_id' => $business->id,
                'status' => ReservationStatus::Completed,
            ]);

            Review::factory()->create([
                'business_id' => $business->id,
                'user_id' => $reservation->customer_id,
                'reservation_id' => $reservation->id,
                'rating' => 4,
                'status' => ReviewStatus::Published,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/businesses');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response
            ->assertOk()
            ->assertJsonPath('data.items.0.rating.count', 3)
            ->assertJsonPath('data.items.0.rating.average', 4.0);

        $this->assertLessThanOrEqual(8, count($queries));
    }

    public function test_rating_average_calculation(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        foreach ([5, 5, 4, 3] as $rating) {
            $reservation = Reservation::factory()->create([
                'business_id' => $business->id,
                'status' => ReservationStatus::Completed,
            ]);

            Review::factory()->create([
                'business_id' => $business->id,
                'user_id' => $reservation->customer_id,
                'reservation_id' => $reservation->id,
                'rating' => $rating,
                'status' => ReviewStatus::Published,
            ]);
        }

        $this->getJson('/api/v1/businesses/'.$business->id)
            ->assertJsonPath('data.rating.average', 4.25)
            ->assertJsonPath('data.rating.count', 4)
            ->assertJsonPath('data.rating.distribution.5', 2)
            ->assertJsonPath('data.rating.distribution.4', 1)
            ->assertJsonPath('data.rating.distribution.3', 1);
    }

    public function test_customer_can_create_review_via_reservation_route(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $reservation = Reservation::factory()->create([
            'customer_id' => $user->id,
            'business_id' => $business->id,
            'status' => ReservationStatus::Completed,
        ]);

        $this->postJson('/api/v1/me/reservations/'.$reservation->id.'/review', [
            'rating' => 5,
            'title' => 'Great club',
            'body' => 'Everything worked well.',
        ], $this->authHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.title', 'Great club');
    }

    public function test_customer_can_report_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create(['status' => ReviewStatus::Published]);

        $this->postJson('/api/v1/reviews/'.$review->id.'/report', [
            'reason' => 'spam',
            'description' => 'Unrelated advertising.',
        ], $this->authHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.reason', 'spam');

        $this->postJson('/api/v1/reviews/'.$review->id.'/report', [
            'reason' => 'spam',
        ], $this->authHeaders($user))->assertStatus(422);
    }

    public function test_admin_can_reject_review_via_status_endpoint(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
        $review = Review::factory()->create(['status' => ReviewStatus::Published]);

        $this->patchJson('/api/v1/admin/reviews/'.$review->id.'/status', [
            'action' => 'reject',
            'reason' => 'Fake review',
        ], $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonPath('data.status', ReviewStatus::Rejected->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'review.rejected',
            'entity_id' => $review->id,
        ]);
    }

    public function test_invalid_moderation_transition_is_rejected(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);
        $review = Review::factory()->create(['status' => ReviewStatus::Pending]);

        $this->patchJson('/api/v1/admin/reviews/'.$review->id.'/status', [
            'action' => 'hide',
        ], $this->authHeaders($admin))->assertStatus(422);
    }

    public function test_staff_cannot_respond_to_review(): void
    {
        $staff = User::factory()->create();
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
        ]);

        $reservation = Reservation::factory()->create([
            'business_id' => $business->id,
            'status' => ReservationStatus::Completed,
        ]);

        $review = Review::factory()->create([
            'business_id' => $business->id,
            'user_id' => $reservation->customer_id,
            'reservation_id' => $reservation->id,
        ]);

        $this->postJson('/api/v1/manage/businesses/'.$business->id.'/reviews/'.$review->id.'/response', [
            'body' => 'Thanks',
        ], $this->authHeaders($staff))->assertForbidden();
    }

    public function test_public_reviews_include_rating_summary(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $reservation = Reservation::factory()->create([
            'business_id' => $business->id,
            'status' => ReservationStatus::Completed,
        ]);

        Review::factory()->create([
            'business_id' => $business->id,
            'user_id' => $reservation->customer_id,
            'reservation_id' => $reservation->id,
            'rating' => 5,
            'status' => ReviewStatus::Published,
        ]);

        $this->getJson('/api/v1/businesses/'.$business->id.'/reviews')
            ->assertOk()
            ->assertJsonPath('data.rating.count', 1)
            ->assertJsonPath('data.rating.average', 5.0)
            ->assertJsonPath('data.rating.distribution.5', 1);
    }
}
