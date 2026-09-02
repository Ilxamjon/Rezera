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

class ReservationCreationTest extends PostgresTestCase
{
    use AuthenticatesUsers;
    use SeedsBusinessHours;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-04 12:00:00', 'Asia/Tashkent'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_user_can_create_reservation(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        $response = $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
                'notes' => 'Near window',
            ],
            $this->authHeaders($customer),
        );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ReservationStatus::Confirmed->value)
            ->assertJsonPath('data.duration_minutes', 120)
            ->assertJsonPath('data.total_amount', 60_000)
            ->assertJsonStructure(['data' => ['reservation_number', 'resource', 'business']]);

        $this->assertDatabaseHas('reservations', [
            'customer_id' => $customer->id,
            'resource_id' => $resource->id,
            'business_id' => $business->id,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'hourly_rate_amount' => 30_000,
            'total_amount' => 60_000,
        ]);
    }

    public function test_unauthenticated_user_cannot_create_reservation(): void
    {
        [$business, $resource] = $this->bookableBusiness();

        $this->postJson('/api/v1/businesses/'.$business->id.'/reservations', [
            'resource_id' => $resource->id,
            'date' => '2026-09-04',
            'start_time' => '20:00',
            'end_time' => '22:00',
        ])->assertUnauthorized();
    }

    public function test_resource_from_another_business_is_rejected(): void
    {
        [$businessA] = $this->bookableBusiness();
        [, $foreignResource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$businessA->id.'/reservations',
            [
                'resource_id' => $foreignResource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        )->assertUnprocessable()->assertJsonValidationErrors(['resource_id']);
    }

    public function test_maintenance_resource_is_rejected(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $resource->update(['status' => ResourceStatus::Maintenance]);
        $customer = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        )->assertUnprocessable()->assertJsonValidationErrors(['resource_id']);
    }

    public function test_overlapping_reservation_returns_conflict(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customerA),
        )->assertCreated();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '21:00',
                'end_time' => '23:00',
            ],
            $this->authHeaders($customerB),
        )
            ->assertConflict()
            ->assertJsonPath('code', 'booking_conflict');

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_adjacent_reservation_is_allowed(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customerA),
        )->assertCreated();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '22:00',
                'end_time' => '23:00',
            ],
            $this->authHeaders($customerB),
        )->assertCreated();

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_overnight_reservation_duration_is_calculated_correctly(): void
    {
        [$business, $resource] = $this->bookableBusiness(hours: ['opens_at' => '18:00', 'closes_at' => '02:00']);
        $customer = User::factory()->create();

        $response = $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '23:00',
                'end_time' => '01:00',
            ],
            $this->authHeaders($customer),
        );

        $response->assertCreated()->assertJsonPath('data.duration_minutes', 120);
    }

    public function test_idempotency_key_prevents_duplicate_reservations(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();
        $key = (string) Str::uuid();
        $headers = array_merge($this->authHeaders($customer), ['Idempotency-Key' => $key]);

        $this->postJson('/api/v1/businesses/'.$business->id.'/reservations', [
            'resource_id' => $resource->id,
            'date' => '2026-09-04',
            'start_time' => '20:00',
            'end_time' => '22:00',
        ], $headers)->assertCreated();

        $this->postJson('/api/v1/businesses/'.$business->id.'/reservations', [
            'resource_id' => $resource->id,
            'date' => '2026-09-04',
            'start_time' => '20:00',
            'end_time' => '22:00',
        ], $headers)->assertCreated();

        $this->assertDatabaseCount('reservations', 1);
    }

    /**
     * @return array{0: Business, 1: Resource}
     */
    private function bookableBusiness(array $hours = ['opens_at' => '10:00', 'closes_at' => '02:00']): array
    {
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
        ]);

        $schedule = collect(range(1, 7))->map(fn (int $weekday): array => [
            'weekday' => $weekday,
            'is_closed' => false,
            'is_open_24h' => false,
            'opens_at' => $hours['opens_at'],
            'closes_at' => $hours['closes_at'],
        ])->all();

        $this->seedBusinessHours($business, $schedule);

        $resource = Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Active,
            'hourly_rate_amount' => 30_000,
            'code' => 'PC-'.Str::upper(Str::random(4)),
        ]);

        return [$business, $resource];
    }
}
