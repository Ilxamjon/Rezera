<?php

namespace Tests\Feature\Api\V1\Reservation;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Models\BookingPolicy;
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

class ReservationSettingsTest extends PostgresTestCase
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

    public function test_owner_can_view_reservation_settings(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $response = $this->getJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservation-settings',
            $this->authHeaders($owner),
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'min_duration_minutes',
                    'max_duration_minutes',
                    'duration_step_minutes',
                    'customer_can_cancel',
                    'allow_same_day_reservations',
                ],
            ]);
    }

    public function test_owner_can_update_reservation_settings(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $response = $this->putJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservation-settings',
            [
                'min_duration_minutes' => 30,
                'max_duration_minutes' => 240,
                'duration_step_minutes' => 30,
                'min_advance_minutes' => 60,
                'max_advance_days' => 14,
                'cancellation_deadline_minutes' => 120,
                'customer_can_cancel' => true,
                'allow_same_day_reservations' => false,
                'max_active_reservations_per_customer' => 2,
            ],
            $this->authHeaders($owner),
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.min_duration_minutes', 30)
            ->assertJsonPath('data.max_active_reservations_per_customer', 2);

        $this->assertDatabaseHas('booking_policies', [
            'business_id' => $business->id,
            'min_duration_minutes' => 30,
            'allow_same_day_reservations' => false,
        ]);
    }

    public function test_staff_cannot_update_reservation_settings(): void
    {
        $business = Business::factory()->create();
        $staff = User::factory()->create();

        BusinessMember::factory()->create([
            'business_id' => $business->id,
            'user_id' => $staff->id,
            'member_role' => BusinessMemberRole::Staff,
            'status' => BusinessMemberStatus::Active,
        ]);

        $this->putJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservation-settings',
            ['min_duration_minutes' => 30],
            $this->authHeaders($staff),
        )->assertForbidden();
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $this->putJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservation-settings',
            [
                'min_duration_minutes' => 120,
                'max_duration_minutes' => 60,
            ],
            $this->authHeaders($owner),
        )->assertUnprocessable()->assertJsonValidationErrors(['min_duration_minutes']);
    }

    public function test_owner_can_reset_reservation_settings(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        BookingPolicy::query()->where('business_id', $business->id)->update([
            'min_duration_minutes' => 15,
        ]);

        $this->postJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservation-settings/reset',
            [],
            $this->authHeaders($owner),
        )
            ->assertOk()
            ->assertJsonPath('data.min_duration_minutes', config('reservation_rules.defaults.min_duration_minutes'));
    }

    public function test_public_reservation_rules_endpoint_returns_safe_fields(): void
    {
        $business = Business::factory()->create();

        $response = $this->getJson('/api/v1/businesses/'.$business->id.'/reservation-rules');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'minimum_duration_minutes',
                    'maximum_duration_minutes',
                    'reservation_step_minutes',
                    'minimum_advance_minutes',
                    'maximum_advance_days',
                    'allow_same_day_reservations',
                    'customer_can_cancel',
                    'cancellation_deadline_minutes',
                    'auto_confirm',
                ],
            ])
            ->assertJsonMissingPath('data.metadata')
            ->assertJsonMissingPath('data.max_active_reservations_per_customer');
    }

    public function test_duration_below_minimum_is_rejected(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $business->bookingPolicy->update([
            'min_duration_minutes' => 60,
            'duration_step_minutes' => 30,
        ]);

        $customer = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '20:30',
            ],
            $this->authHeaders($customer),
        )
            ->assertUnprocessable()
            ->assertJsonPath('code', 'reservation_duration_too_short');
    }

    public function test_invalid_duration_step_is_rejected(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $business->bookingPolicy->update([
            'min_duration_minutes' => 30,
            'duration_step_minutes' => 30,
        ]);

        $customer = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:15',
                'end_time' => '21:15',
            ],
            $this->authHeaders($customer),
        )
            ->assertUnprocessable()
            ->assertJsonPath('code', 'reservation_start_time_invalid');
    }

    public function test_same_day_reservation_disabled_is_rejected(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $business->bookingPolicy->update([
            'allow_same_day_reservations' => false,
            'min_advance_minutes' => 0,
            'duration_step_minutes' => 60,
        ]);

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
        )
            ->assertUnprocessable()
            ->assertJsonPath('code', 'same_day_reservations_disabled');
    }

    public function test_customer_active_limit_is_enforced(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        $business->bookingPolicy->update([
            'max_active_reservations_per_customer' => 1,
            'duration_step_minutes' => 60,
        ]);

        Reservation::factory()->create([
            'business_id' => $business->id,
            'resource_id' => $resource->id,
            'customer_id' => $customer->id,
            'status' => ReservationStatus::Confirmed,
            'start_at' => CarbonImmutable::parse('2026-09-04 18:00:00', 'Asia/Tashkent')->utc(),
            'end_at' => CarbonImmutable::parse('2026-09-04 19:00:00', 'Asia/Tashkent')->utc(),
        ]);

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '22:00',
            ],
            $this->authHeaders($customer),
        )
            ->assertUnprocessable()
            ->assertJsonPath('code', 'customer_reservation_limit_reached');
    }

    public function test_cancellation_deadline_is_enforced_for_customers(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        $business->bookingPolicy->update([
            'cancellation_deadline_minutes' => 120,
            'customer_can_cancel' => true,
        ]);

        $reservation = Reservation::factory()->create([
            'business_id' => $business->id,
            'resource_id' => $resource->id,
            'customer_id' => $customer->id,
            'status' => ReservationStatus::Confirmed,
            'start_at' => CarbonImmutable::parse('2026-09-04 13:00:00', 'Asia/Tashkent')->utc(),
            'end_at' => CarbonImmutable::parse('2026-09-04 14:00:00', 'Asia/Tashkent')->utc(),
        ]);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/cancel',
            ['reason' => 'Changed plans'],
            $this->authHeaders($customer),
        )
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cancellation_deadline_passed');
    }

    public function test_manual_confirmation_mode_creates_pending_reservation(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $business->bookingPolicy->update([
            'confirmation_mode' => ConfirmationMode::Manual,
        ]);

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
        )
            ->assertCreated()
            ->assertJsonPath('data.status', ReservationStatus::Pending->value);
    }

    public function test_buffer_blocks_adjacent_reservation(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customerA = User::factory()->create();
        $customerB = User::factory()->create();

        $business->bookingPolicy->update([
            'buffer_minutes' => 15,
            'duration_step_minutes' => 60,
        ]);

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '18:00',
                'end_time' => '19:00',
            ],
            $this->authHeaders($customerA),
        )->assertCreated();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '19:00',
                'end_time' => '20:00',
            ],
            $this->authHeaders($customerB),
        )
            ->assertConflict()
            ->assertJsonPath('code', 'booking_conflict');
    }

    public function test_resource_duration_override_is_applied(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $business->bookingPolicy->update([
            'min_duration_minutes' => 120,
            'duration_step_minutes' => 60,
        ]);

        $resource->update([
            'min_duration_minutes' => 60,
        ]);

        $customer = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '20:00',
                'end_time' => '21:00',
            ],
            $this->authHeaders($customer),
        )->assertCreated();
    }

    public function test_advance_booking_too_soon_is_rejected(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $business->bookingPolicy->update([
            'min_advance_minutes' => 120,
            'duration_step_minutes' => 60,
        ]);

        $customer = User::factory()->create();

        $this->postJson(
            '/api/v1/businesses/'.$business->id.'/reservations',
            [
                'resource_id' => $resource->id,
                'date' => '2026-09-04',
                'start_time' => '13:00',
                'end_time' => '14:00',
            ],
            $this->authHeaders($customer),
        )
            ->assertUnprocessable()
            ->assertJsonPath('code', 'reservation_too_soon');
    }

    public function test_settings_update_is_audited(): void
    {
        $business = Business::factory()->create();
        $owner = User::query()->findOrFail($business->created_by_user_id);

        $this->putJson(
            '/api/v1/manage/businesses/'.$business->id.'/reservation-settings',
            ['min_duration_minutes' => 45, 'duration_step_minutes' => 15, 'max_duration_minutes' => 480],
            $this->authHeaders($owner),
        )->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'reservation_settings.updated',
            'entity_type' => 'booking_policy',
            'actor_user_id' => $owner->id,
        ]);
    }

    public function test_customer_can_cancel_before_deadline(): void
    {
        [$business, $resource] = $this->bookableBusiness();
        $customer = User::factory()->create();

        $business->bookingPolicy->update([
            'cancellation_deadline_minutes' => 60,
            'customer_can_cancel' => true,
        ]);

        $reservation = Reservation::factory()->create([
            'business_id' => $business->id,
            'resource_id' => $resource->id,
            'customer_id' => $customer->id,
            'status' => ReservationStatus::Confirmed,
            'start_at' => CarbonImmutable::parse('2026-09-04 20:00:00', 'Asia/Tashkent')->utc(),
            'end_at' => CarbonImmutable::parse('2026-09-04 22:00:00', 'Asia/Tashkent')->utc(),
        ]);

        $this->postJson(
            '/api/v1/me/reservations/'.$reservation->id.'/cancel',
            ['reason' => 'Changed plans'],
            $this->authHeaders($customer),
        )
            ->assertOk()
            ->assertJsonPath('data.status', ReservationStatus::Cancelled->value);
    }

    /**
     * @return array{0: Business, 1: Resource}
     */
    private function bookableBusiness(): array
    {
        $business = Business::factory()->create([
            'status' => BusinessStatus::Approved,
            'timezone' => 'Asia/Tashkent',
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

        return [$business, $resource];
    }
}
