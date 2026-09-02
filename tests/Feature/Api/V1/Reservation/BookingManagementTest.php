<?php

namespace Tests\Feature\Api\V1\Reservation;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\SeedsBusinessHours;

class BookingManagementTest extends PostgresTestCase
{
    use AuthenticatesUsers;
    use SeedsBusinessHours;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_customer_can_filter_upcoming_and_past_reservations(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 12:00:00', 'Asia/Tashkent'));

        $customer = User::factory()->create();
        $business = $this->createBusiness();

        $upcoming = $this->createReservation($business, $customer, '2026-09-05 18:00:00', ReservationStatus::Confirmed);
        $past = $this->createReservation($business, $customer, '2026-09-04 18:00:00', ReservationStatus::Completed);

        $this->getJson('/api/v1/me/reservations?scope=upcoming', $this->authHeaders($customer))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $upcoming->id);

        $this->getJson('/api/v1/me/reservations?scope=past', $this->authHeaders($customer))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $past->id);
    }

    public function test_customer_upcoming_endpoint_returns_active_future_reservations(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 12:00:00', 'Asia/Tashkent'));

        $customer = User::factory()->create();
        $business = $this->createBusiness();
        $reservation = $this->createReservation($business, $customer, '2026-09-06 10:00:00');

        $this->getJson('/api/v1/me/reservations/upcoming', $this->authHeaders($customer))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $reservation->id);
    }

    public function test_customer_cannot_cancel_completed_reservation(): void
    {
        $customer = User::factory()->create();
        $business = $this->createBusiness();
        $reservation = $this->createReservation($business, $customer, '2026-09-06 10:00:00', ReservationStatus::Completed);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/cancel',
            ['reason' => 'Too late'],
            $this->authHeaders($customer),
        )->assertUnprocessable();
    }

    public function test_customer_cancel_is_idempotent_when_already_cancelled(): void
    {
        $customer = User::factory()->create();
        $business = $this->createBusiness();
        $reservation = $this->createReservation($business, $customer, '2026-09-06 10:00:00', ReservationStatus::Cancelled);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/cancel',
            ['reason' => 'Again'],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Cancelled->value);
    }

    public function test_business_can_search_by_reservation_number_and_customer_name(): void
    {
        $owner = User::factory()->create();
        $customer = User::factory()->create(['name' => 'Ilham Karimov']);
        $business = $this->createBusiness($owner);
        $reservation = $this->createReservation($business, $customer, '2026-09-06 10:00:00');

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations?search='.$reservation->reservation_number,
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations?search=Ilham',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_business_can_filter_by_date_status_and_resource(): void
    {
        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);
        $resourceA = $this->createResource($business, 'PC-A');
        $resourceB = $this->createResource($business, 'PC-B');

        $match = $this->createReservation($business, $customer, '2026-09-05 10:00:00', ReservationStatus::Pending, $resourceA);
        $this->createReservation($business, $customer, '2026-09-05 12:00:00', ReservationStatus::Confirmed, $resourceB);
        $this->createReservation($business, $customer, '2026-09-06 10:00:00', ReservationStatus::Pending, $resourceA);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations?date=2026-09-05&status=pending&resource_id='.$resourceA->id,
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $match->id);
    }

    public function test_today_endpoint_uses_business_timezone_for_overnight_reservation(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 01:30:00', 'Asia/Tashkent'));

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);
        $resource = $this->createResource($business);

        $overnight = $this->createReservation(
            $business,
            $customer,
            '2026-09-04 22:00:00',
            ReservationStatus::Confirmed,
            $resource,
            240,
        );

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/today',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $overnight->id);
    }

    public function test_upcoming_endpoint_excludes_cancelled_reservations(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 12:00:00', 'Asia/Tashkent'));

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);

        $active = $this->createReservation($business, $customer, '2026-09-06 10:00:00', ReservationStatus::Confirmed);
        $this->createReservation($business, $customer, '2026-09-07 10:00:00', ReservationStatus::Cancelled);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/upcoming',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $active->id);
    }

    public function test_status_transitions_for_business_operations(): void
    {
        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);
        $reservation = $this->createReservation($business, $customer, '2026-09-06 10:00:00', ReservationStatus::Pending);

        $this->patchJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/'.$reservation->id,
            ['status' => ReservationStatus::Confirmed->value],
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Confirmed->value);

        $this->assertNotNull($reservation->fresh()->confirmed_at);

        $this->patchJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/'.$reservation->id,
            ['status' => ReservationStatus::Completed->value],
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Completed->value);

        $completed = $this->createReservation($business, $customer, '2026-09-07 10:00:00', ReservationStatus::Confirmed);

        $this->patchJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/'.$completed->id,
            ['status' => ReservationStatus::NoShow->value],
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::NoShow->value);
    }

    public function test_staff_can_complete_but_cannot_confirm_or_cancel(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
        ]);

        $pending = $this->createReservation($business, $customer, '2026-09-06 10:00:00', ReservationStatus::Pending);
        $confirmed = $this->createReservation($business, $customer, '2026-09-07 10:00:00', ReservationStatus::Confirmed);

        $this->patchJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/'.$pending->id,
            ['status' => ReservationStatus::Confirmed->value],
            $this->authHeaders($staff),
        )->assertForbidden();

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/'.$confirmed->id.'/cancel',
            ['reason' => 'Maintenance'],
            $this->authHeaders($staff),
        )->assertForbidden();

        $this->patchJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/'.$confirmed->id,
            ['status' => ReservationStatus::Completed->value],
            $this->authHeaders($staff),
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Completed->value);
    }

    public function test_cross_business_access_is_blocked(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $customer = User::factory()->create();
        $businessA = $this->createBusiness($ownerA);
        $businessB = $this->createBusiness($ownerB);
        $reservationB = $this->createReservation($businessB, $customer, '2026-09-06 10:00:00');

        $this->getJson(
            '/api/v1/manage/businesses/'.$businessA->id.'/reservations/'.$reservationB->id,
            $this->authHeaders($ownerA),
        )->assertNotFound();

        $this->patchJson(
            '/api/v1/manage/businesses/'.$businessA->id.'/reservations/'.$reservationB->id,
            ['status' => ReservationStatus::Completed->value],
            $this->authHeaders($ownerA),
        )->assertNotFound();
    }

    public function test_resource_from_another_business_cannot_be_used_as_filter(): void
    {
        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $businessA = $this->createBusiness($owner);
        $businessB = $this->createBusiness();
        $foreignResource = $this->createResource($businessB);

        $this->getJson(
            '/api/v1/manage/businesses/'.$businessA->id.'/reservations?resource_id='.$foreignResource->id,
            $this->authHeaders($owner),
        )->assertUnprocessable()->assertJsonValidationErrors(['resource_id']);
    }

    public function test_dashboard_summary_uses_aggregates(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 12:00:00', 'Asia/Tashkent'));

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);

        $this->createReservation($business, $customer, '2026-09-05 10:00:00', ReservationStatus::Pending);
        $this->createReservation($business, $customer, '2026-09-05 14:00:00', ReservationStatus::Confirmed);
        $this->createReservation($business, $customer, '2026-09-06 10:00:00', ReservationStatus::Confirmed);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/dashboard',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.today.total', 2)
            ->assertJsonPath('data.today.pending', 1)
            ->assertJsonPath('data.today.confirmed', 1)
            ->assertJsonPath('data.upcoming', 2);
    }

    public function test_business_cancel_stores_reason_and_timestamp(): void
    {
        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);
        $reservation = $this->createReservation($business, $customer, '2026-09-06 10:00:00', ReservationStatus::Confirmed);

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/'.$reservation->id.'/cancel',
            ['reason' => 'Resource maintenance'],
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Cancelled->value);

        $reservation->refresh();
        $this->assertSame('Resource maintenance', $reservation->cancellation_reason);
        $this->assertNotNull($reservation->cancelled_at);
    }

    private function createBusiness(?User $owner = null): Business
    {
        $owner ??= User::factory()->create();

        $business = Business::factory()->create([
            'created_by_user_id' => $owner->id,
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        $this->seedBusinessHours($business, collect(range(1, 7))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'is_closed' => false,
            'is_open_24h' => false,
            'opens_at' => '10:00',
            'closes_at' => '02:00',
        ])->all());

        return $business;
    }

    private function createResource(Business $business, ?string $code = null): Resource
    {
        return Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 30_000,
            'code' => $code ?? 'PC-'.Str::upper(Str::random(4)),
        ]);
    }

    private function createReservation(
        Business $business,
        User $customer,
        string $localStart,
        ReservationStatus $status = ReservationStatus::Confirmed,
        ?Resource $resource = null,
        int $durationMinutes = 120,
    ): Reservation {
        $resource ??= $this->createResource($business);

        $start = CarbonImmutable::parse($localStart, $business->timezone)->utc();
        $end = $start->addMinutes($durationMinutes);

        return Reservation::factory()->forResource($resource)->create([
            'customer_id' => $customer->id,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'reservation_number' => 'RZ-'.str_replace('-', '', substr($localStart, 0, 10)).'-'.fake()->unique()->numerify('######'),
            'start_at' => $start,
            'end_at' => $end,
            'duration_minutes' => $durationMinutes,
            'status' => $status,
            'hourly_rate_amount' => 30_000,
            'subtotal_amount' => (int) round(30_000 * $durationMinutes / 60),
            'discount_amount' => 0,
            'total_amount' => (int) round(30_000 * $durationMinutes / 60),
        ]);
    }
}
