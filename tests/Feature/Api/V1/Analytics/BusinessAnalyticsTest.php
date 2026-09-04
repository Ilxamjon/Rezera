<?php

namespace Tests\Feature\Api\V1\Analytics;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\PostgresTestCase;
use Tests\Support\AssignsBusinessSubscription;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\SeedsBusinessHours;

class BusinessAnalyticsTest extends PostgresTestCase
{
    use AssignsBusinessSubscription;
    use AuthenticatesUsers;
    use SeedsBusinessHours;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 12:00:00', 'Asia/Tashkent'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_owner_can_view_analytics_overview(): void
    {
        [$business, , $owner] = $this->analyticsBusiness();
        $customer = User::factory()->create();

        $this->seedReservations($business, $customer);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/analytics/overview?preset=last_30_days',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.reservations.total', 3)
            ->assertJsonPath('data.reservations.completed', 2)
            ->assertJsonPath('data.revenue.net_reservation_value', 250000);
    }

    public function test_staff_can_view_operational_analytics_but_not_revenue(): void
    {
        [$business, , $owner, $staff] = $this->analyticsBusiness(includeStaff: true);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/analytics/reservations?preset=last_30_days',
            $this->authHeaders($staff),
        )->assertOk();

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/analytics/revenue?preset=last_30_days',
            $this->authHeaders($staff),
        )->assertForbidden();
    }

    public function test_customer_cannot_access_business_analytics(): void
    {
        [$business] = $this->analyticsBusiness();
        $customer = User::factory()->create();

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/analytics/overview?preset=last_30_days',
            $this->authHeaders($customer),
        )->assertForbidden();
    }

    public function test_cross_business_analytics_access_is_blocked(): void
    {
        [$businessA, , $ownerA] = $this->analyticsBusiness();
        [$businessB] = $this->analyticsBusiness();

        $this->getJson(
            '/api/v1/manage/businesses/'.$businessB->id.'/analytics/overview?preset=last_30_days',
            $this->authHeaders($ownerA),
        )->assertForbidden();
    }

    public function test_revenue_analytics_uses_payment_records(): void
    {
        [$business, $resource, $owner] = $this->analyticsBusiness();
        $customer = User::factory()->create();

        $reservation = $this->createReservation($business, $resource, $customer, 100000, ReservationStatus::Completed);

        Payment::factory()->create([
            'business_id' => $business->id,
            'reservation_id' => $reservation->id,
            'user_id' => $customer->id,
            'status' => PaymentStatus::Paid,
            'amount' => 100000,
            'paid_at' => CarbonImmutable::parse('2026-09-04 18:00:00', 'Asia/Tashkent')->utc(),
        ]);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/analytics/revenue?preset=last_30_days',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.metrics.amount_paid', 100000)
            ->assertJsonPath('data.metrics.successful_payments', 1);
    }

    public function test_resource_ranking_rejects_unsafe_sort(): void
    {
        [$business, , $owner] = $this->analyticsBusiness();

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/analytics/resources/ranking?preset=last_30_days&sort=malicious_column',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.sort', 'reservations_total');
    }

    public function test_custom_date_range_validation(): void
    {
        [$business, , $owner] = $this->analyticsBusiness();

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/analytics/overview?preset=custom&from=2026-09-10',
            $this->authHeaders($owner),
        )->assertUnprocessable();
    }

    public function test_platform_admin_can_view_admin_analytics(): void
    {
        $admin = User::factory()->create(['platform_role' => PlatformRole::Admin]);

        $this->getJson('/api/v1/admin/analytics/overview', $this->authHeaders($admin))
            ->assertOk()
            ->assertJsonStructure(['data' => ['users', 'businesses', 'reservations', 'revenue']]);
    }

    /**
     * @return array{0: Business, 1: resource, 2: User, 3?: User}
     */
    private function analyticsBusiness(bool $includeStaff = false): array
    {
        $owner = User::factory()->create();
        $business = Business::factory()->create([
            'created_by_user_id' => $owner->id,
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        $this->seedBusinessHours($business, collect(range(1, 7))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'opens_at' => '10:00',
            'closes_at' => '02:00',
            'is_closed' => false,
            'is_open_24h' => false,
        ]));

        $resource = Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 50_000,
        ]);

        $this->assignProSubscription($business);

        if ($includeStaff) {
            $staff = User::factory()->create();
            BusinessMember::factory()->create([
                'business_id' => $business->id,
                'user_id' => $staff->id,
                'member_role' => BusinessMemberRole::Staff,
            ]);

            return [$business, $resource, $owner, $staff];
        }

        return [$business, $resource, $owner];
    }

    private function seedReservations(Business $business, User $customer): void
    {
        $resource = Resource::query()->where('business_id', $business->id)->first();

        $this->createReservation($business, $resource, $customer, 100000, ReservationStatus::Completed);
        $this->createReservation($business, $resource, $customer, 150000, ReservationStatus::Completed);
        $this->createReservation($business, $resource, $customer, 50000, ReservationStatus::Cancelled);
    }

    private function createReservation(
        Business $business,
        Resource $resource,
        User $customer,
        int $totalAmount,
        ReservationStatus $status,
    ): Reservation {
        $start = CarbonImmutable::parse('2026-09-04 18:00:00', $business->timezone)->utc();
        $end = $start->addHours(2);

        return Reservation::factory()->forResource($resource)->create([
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'status' => $status,
            'start_at' => $start,
            'end_at' => $end,
            'duration_minutes' => 120,
            'subtotal_amount' => $totalAmount,
            'discount_amount' => 0,
            'total_amount' => $totalAmount,
            'completed_at' => $status === ReservationStatus::Completed ? $end : null,
            'currency' => 'UZS',
        ]);
    }
}
