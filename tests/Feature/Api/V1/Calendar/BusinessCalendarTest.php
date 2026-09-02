<?php

namespace Tests\Feature\Api\V1\Calendar;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Reservations\Enums\CheckInMethod;
use App\Domain\Reservations\Enums\ReservationSessionStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Reservation;
use App\Models\ReservationSession;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\SeedsBusinessHours;

class BusinessCalendarTest extends PostgresTestCase
{
    use AuthenticatesUsers;
    use SeedsBusinessHours;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_owner_can_view_daily_resource_calendar_with_blocks(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 12:00:00', 'Asia/Tashkent'));

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);

        $pc1 = $this->createResource($business, 'PC-1');
        $pc2 = $this->createResource($business, 'PC-2');
        $ps5 = $this->createResource($business, 'PS5-VIP');

        $this->createReservation($business, $customer, '2026-09-02 10:00:00', ReservationStatus::Confirmed, $pc1);
        $this->createReservation($business, $customer, '2026-09-02 14:00:00', ReservationStatus::Confirmed, $pc1);
        $this->createReservation($business, $customer, '2026-09-02 13:00:00', ReservationStatus::Confirmed, $pc2);

        $active = $this->createReservation($business, $customer, '2026-09-02 12:00:00', ReservationStatus::CheckedIn, $ps5);
        ReservationSession::query()->create([
            'reservation_id' => $active->id,
            'business_id' => $business->id,
            'resource_id' => $ps5->id,
            'user_id' => $customer->id,
            'started_at' => CarbonImmutable::parse('2026-09-02 12:00:00', $business->timezone)->utc(),
            'start_method' => CheckInMethod::Staff,
            'status' => ReservationSessionStatus::Active->value,
        ]);

        $response = $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/calendar/day?date=2026-09-02',
            $this->authHeaders($owner),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.view', 'day')
            ->assertJsonPath('data.date', '2026-09-02')
            ->assertJsonPath('data.is_closed', false)
            ->assertJsonCount(3, 'data.resources');

        $pc1Blocks = collect($response->json('data.resources'))
            ->firstWhere('resource.code', 'PC-1')['blocks'];

        $this->assertTrue(collect($pc1Blocks)->contains(fn (array $block): bool => $block['type'] === 'booked' && $block['start_time'] === '10:00'));
        $this->assertTrue(collect($pc1Blocks)->contains(fn (array $block): bool => $block['type'] === 'available'));
        $this->assertTrue(collect($pc1Blocks)->contains(fn (array $block): bool => $block['type'] === 'booked' && $block['start_time'] === '14:00'));

        $ps5Blocks = collect($response->json('data.resources'))
            ->firstWhere('resource.code', 'PS5-VIP')['blocks'];

        $this->assertTrue(collect($ps5Blocks)->contains(fn (array $block): bool => $block['type'] === 'active'));
    }

    public function test_week_calendar_returns_seven_day_summary(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 12:00:00', 'Asia/Tashkent'));

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);
        $resource = $this->createResource($business);

        $this->createReservation($business, $customer, '2026-09-02 10:00:00', ReservationStatus::Confirmed, $resource);
        $this->createReservation($business, $customer, '2026-09-05 10:00:00', ReservationStatus::Confirmed, $resource);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/calendar/week?date=2026-09-02',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.view', 'week')
            ->assertJsonCount(7, 'data.days')
            ->assertJsonPath('data.days.0.date', '2026-08-31')
            ->assertJsonPath('data.days.6.date', '2026-09-06')
            ->assertJsonPath('data.days.2.summary.reservations_total', 1)
            ->assertJsonPath('data.days.5.summary.reservations_total', 1);
    }

    public function test_timeline_lists_reservations_in_range(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 12:00:00', 'Asia/Tashkent'));

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);
        $resource = $this->createResource($business);

        $first = $this->createReservation($business, $customer, '2026-09-02 10:00:00', ReservationStatus::Confirmed, $resource);
        $second = $this->createReservation($business, $customer, '2026-09-03 10:00:00', ReservationStatus::Confirmed, $resource);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/calendar/timeline?from=2026-09-02&to=2026-09-03',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.view', 'timeline')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.id', $first->id)
            ->assertJsonPath('data.items.1.id', $second->id)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        ['operational_status', 'resource', 'customer'],
                    ],
                ],
            ]);
    }

    public function test_resource_schedule_returns_multi_day_blocks(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-02 12:00:00', 'Asia/Tashkent'));

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);
        $resource = $this->createResource($business, 'PC-12');

        $this->createReservation($business, $customer, '2026-09-02 14:00:00', ReservationStatus::Confirmed, $resource);

        $response = $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/calendar/resources/'.$resource->id.'?from=2026-09-02&to=2026-09-03',
            $this->authHeaders($owner),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.view', 'resource')
            ->assertJsonPath('data.resource.code', 'PC-12')
            ->assertJsonCount(2, 'data.days');

        $dayBlocks = $response->json('data.days.0.blocks');
        $this->assertTrue(collect($dayBlocks)->contains(fn (array $block): bool => $block['type'] === 'booked' && $block['start_time'] === '14:00'));
    }

    public function test_staff_can_view_calendar(): void
    {
        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $business = $this->createBusiness($owner);

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
        ]);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/calendar/day',
            $this->authHeaders($staff),
        )->assertOk();
    }

    public function test_customer_cannot_access_calendar(): void
    {
        $customer = User::factory()->create();
        $business = $this->createBusiness();

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/calendar/day',
            $this->authHeaders($customer),
        )->assertForbidden();
    }

    public function test_cross_business_calendar_access_is_blocked(): void
    {
        $ownerA = User::factory()->create();
        $businessA = $this->createBusiness($ownerA);
        $businessB = $this->createBusiness();

        $this->getJson(
            '/api/v1/manage/businesses/'.$businessB->id.'/calendar/day',
            $this->authHeaders($ownerA),
        )->assertForbidden();
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
            'opens_at' => '09:00',
            'closes_at' => '22:00',
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
