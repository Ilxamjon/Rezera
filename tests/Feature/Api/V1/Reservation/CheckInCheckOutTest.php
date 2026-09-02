<?php

namespace Tests\Feature\Api\V1\Reservation;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Reservations\Enums\ReservationSessionStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\BookingPolicy;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use App\Services\Reservations\QrTokenService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\SeedsBusinessHours;

class CheckInCheckOutTest extends PostgresTestCase
{
    use AuthenticatesUsers;
    use SeedsBusinessHours;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 19:30:00', 'Asia/Tashkent'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_customer_can_check_in_confirmed_reservation(): void
    {
        [$business, $resource, $reservation, $customer] = $this->confirmedReservation();

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/check-in',
            [],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::CheckedIn->value)
            ->assertJsonPath('data.operational_status', 'checked_in')
            ->assertJsonPath('data.check_in_method', 'customer');

        $this->assertDatabaseHas('reservation_sessions', [
            'reservation_id' => $reservation->id,
            'status' => ReservationSessionStatus::Active->value,
        ]);
    }

    public function test_customer_cannot_check_in_another_users_reservation(): void
    {
        [, , $reservation] = $this->confirmedReservation();
        $other = User::factory()->create();

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/check-in',
            [],
            $this->authHeaders($other),
        )->assertForbidden();
    }

    public function test_duplicate_check_in_is_idempotent(): void
    {
        [, , $reservation, $customer] = $this->confirmedReservation();
        $headers = $this->authHeaders($customer);

        $this->postJson('/api/v1/me/reservations/'.$reservation->id.'/check-in', [], $headers)->assertOk();
        $this->postJson('/api/v1/me/reservations/'.$reservation->id.'/check-in', [], $headers)->assertOk();

        $this->assertDatabaseCount('reservation_sessions', 1);
    }

    public function test_qr_check_in_validates_resource_token(): void
    {
        [$business, $resource, $reservation, $customer] = $this->confirmedReservation();
        $token = app(QrTokenService::class)->generate($resource);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/check-in/qr',
            ['qr_token' => $token->token],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.check_in_method', 'qr');
    }

    public function test_invalid_qr_token_rejected(): void
    {
        [, , $reservation, $customer] = $this->confirmedReservation();

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/check-in/qr',
            ['qr_token' => str_repeat('A', 48)],
            $this->authHeaders($customer),
        )->assertUnprocessable()->assertJsonValidationErrors(['qr_token']);
    }

    public function test_wrong_resource_qr_rejected(): void
    {
        [$business, $resource, $reservation, $customer] = $this->confirmedReservation();
        $otherResource = Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 30_000,
        ]);
        $token = app(QrTokenService::class)->generate($otherResource);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/check-in/qr',
            ['qr_token' => $token->token],
            $this->authHeaders($customer),
        )->assertUnprocessable()->assertJsonValidationErrors(['qr_token']);
    }

    public function test_staff_can_check_in_customer(): void
    {
        [$business, , $reservation, $customer, $staff] = $this->confirmedReservation(withStaff: true);

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservations/'.$reservation->id.'/check-in',
            [],
            $this->authHeaders($staff),
        )
            ->assertOk()
            ->assertJsonPath('data.check_in_method', 'staff');
    }

    public function test_check_out_completes_reservation_and_session(): void
    {
        [$business, , $reservation, $customer] = $this->confirmedReservation();
        $headers = $this->authHeaders($customer);

        $this->postJson('/api/v1/me/reservations/'.$reservation->id.'/check-in', [], $headers)->assertOk();

        $this->postJson('/api/v1/me/reservations/'.$reservation->id.'/check-out', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Completed->value)
            ->assertJsonPath('data.operational_status', 'checked_out');

        $this->assertDatabaseHas('reservation_sessions', [
            'reservation_id' => $reservation->id,
            'status' => ReservationSessionStatus::Completed->value,
        ]);
    }

    public function test_cannot_check_out_without_check_in(): void
    {
        [, , $reservation, $customer] = $this->confirmedReservation();

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/check-out',
            [],
            $this->authHeaders($customer),
        )->assertUnprocessable();
    }

    public function test_operations_today_endpoint_returns_data(): void
    {
        [$business, , $reservation, $customer, $staff] = $this->confirmedReservation(withStaff: true);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/operations/today',
            $this->authHeaders($staff),
        )
            ->assertOk()
            ->assertJsonPath('data.counts.reservations_today', 1);
    }

    public function test_resource_occupancy_shows_occupied_resource(): void
    {
        [$business, $resource, $reservation, $customer, $staff] = $this->confirmedReservation(withStaff: true);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/check-in',
            [],
            $this->authHeaders($customer),
        )->assertOk();

        $response = $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/resources/occupancy',
            $this->authHeaders($staff),
        );

        $response->assertOk();
        $occupied = collect($response->json('data'))->firstWhere('resource.id', $resource->id);
        $this->assertSame('occupied', $occupied['occupancy_state']);
    }

    public function test_cross_business_check_in_blocked(): void
    {
        [$businessA, , $reservation] = $this->confirmedReservation();
        [$businessB, , , , $staffB] = $this->confirmedReservation(withStaff: true);

        $this->postJson(
            '/api/v1/manage/businesses/'.$businessB->id.'/reservations/'.$reservation->id.'/check-in',
            [],
            $this->authHeaders($staffB),
        )->assertNotFound();
    }

    /**
     * @return array{0: Business, 1: Resource, 2: Reservation, 3: User, 4?: User}
     */
    private function confirmedReservation(bool $withStaff = false): array
    {
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        BookingPolicy::factory()->create([
            'business_id' => $business->id,
            'check_in_early_minutes' => 30,
            'no_show_grace_minutes' => 30,
        ]);

        $schedule = collect(range(1, 7))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'is_closed' => false,
            'is_open_24h' => false,
            'opens_at' => '10:00',
            'closes_at' => '02:00',
        ])->all();

        $this->seedBusinessHours($business, $schedule);

        $resource = Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 30_000,
            'code' => 'PC-'.Str::upper(Str::random(4)),
        ]);

        $customer = User::factory()->create();
        $start = CarbonImmutable::parse('2026-09-04 20:00:00', 'Asia/Tashkent')->utc();
        $end = CarbonImmutable::parse('2026-09-04 22:00:00', 'Asia/Tashkent')->utc();

        $reservation = Reservation::factory()->forResource($resource)->create([
            'customer_id' => $customer->id,
            'status' => ReservationStatus::Confirmed,
            'start_at' => $start,
            'end_at' => $end,
            'duration_minutes' => 120,
            'subtotal_amount' => 60_000,
            'discount_amount' => 0,
            'total_amount' => 60_000,
        ]);

        if (! $withStaff) {
            return [$business, $resource, $reservation, $customer];
        }

        $staff = User::factory()->create();
        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
        ]);

        return [$business, $resource, $reservation, $customer, $staff];
    }
}
