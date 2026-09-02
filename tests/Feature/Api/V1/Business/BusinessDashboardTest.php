<?php

namespace Tests\Feature\Api\V1\Business;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\CheckInMethod;
use App\Domain\Reservations\Enums\ReservationSessionStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationSession;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;
use Tests\Support\SeedsBusinessHours;

class BusinessDashboardTest extends PostgresTestCase
{
    use AuthenticatesUsers;
    use SeedsBusinessHours;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
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
            ->assertJsonPath('data.business.id', $business->id)
            ->assertJsonPath('data.business.name', $business->name)
            ->assertJsonPath('data.today.total', 2)
            ->assertJsonPath('data.today.pending', 1)
            ->assertJsonPath('data.today.confirmed', 1)
            ->assertJsonPath('data.upcoming', 2)
            ->assertJsonStructure([
                'data' => [
                    'business',
                    'date',
                    'generated_at',
                    'today',
                    'upcoming',
                    'operations',
                    'resources',
                    'revenue_today',
                    'upcoming_reservations',
                ],
            ]);
    }

    public function test_dashboard_includes_active_sessions_resource_snapshot_and_upcoming_list(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 12:00:00', 'Asia/Tashkent'));

        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);

        $availableResource = $this->createResource($business, 'PC-01');
        $occupiedResource = $this->createResource($business, 'PS5-VIP');
        Resource::factory()->create([
            'business_id' => $business->id,
            'status' => ResourceStatus::Maintenance,
            'code' => 'MAINT-1',
        ]);

        $activeReservation = $this->createReservation(
            $business,
            $customer,
            '2026-09-05 10:00:00',
            ReservationStatus::CheckedIn,
            $occupiedResource,
        );

        ReservationSession::query()->create([
            'reservation_id' => $activeReservation->id,
            'business_id' => $business->id,
            'resource_id' => $occupiedResource->id,
            'user_id' => $customer->id,
            'started_at' => CarbonImmutable::parse('2026-09-05 10:00:00', $business->timezone)->utc(),
            'start_method' => CheckInMethod::Staff,
            'status' => ReservationSessionStatus::Active->value,
        ]);

        $upcoming = $this->createReservation(
            $business,
            $customer,
            '2026-09-05 14:00:00',
            ReservationStatus::Confirmed,
            $availableResource,
        );

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/dashboard?upcoming_limit=5',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.operations.active_sessions', 1)
            ->assertJsonPath('data.today.active_sessions', 1)
            ->assertJsonPath('data.resources.total', 3)
            ->assertJsonPath('data.resources.available', 1)
            ->assertJsonPath('data.resources.occupied', 1)
            ->assertJsonPath('data.resources.unavailable', 1)
            ->assertJsonCount(1, 'data.upcoming_reservations')
            ->assertJsonPath('data.upcoming_reservations.0.id', $upcoming->id)
            ->assertJsonPath('data.upcoming_reservations.0.start_time', '14:00')
            ->assertJsonPath('data.upcoming_reservations.0.resource.name', $availableResource->name);
    }

    public function test_staff_sees_operational_dashboard_without_revenue(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 12:00:00', 'Asia/Tashkent'));

        $owner = User::factory()->create();
        $staff = User::factory()->create();
        $customer = User::factory()->create();
        $business = $this->createBusiness($owner);

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
        ]);

        $reservation = $this->createReservation($business, $customer, '2026-09-05 10:00:00', ReservationStatus::Completed);

        Payment::factory()->create([
            'business_id' => $business->id,
            'reservation_id' => $reservation->id,
            'user_id' => $customer->id,
            'amount' => 120_000,
            'status' => PaymentStatus::Paid,
            'paid_at' => CarbonImmutable::parse('2026-09-05 11:00:00', $business->timezone)->utc(),
        ]);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/dashboard',
            $this->authHeaders($staff),
        )
            ->assertOk()
            ->assertJsonPath('data.revenue_today', null);

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/dashboard',
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.revenue_today.amount_paid', 120_000);
    }

    public function test_customer_cannot_access_business_dashboard(): void
    {
        $customer = User::factory()->create();
        $business = $this->createBusiness();

        $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/dashboard',
            $this->authHeaders($customer),
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
