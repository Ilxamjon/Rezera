<?php

namespace Tests\Feature\Database;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Tests\PostgresTestCase;

class ReservationOverlapTest extends PostgresTestCase
{
    private Resource $resource;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue($this->postgresExtensionEnabled('btree_gist'));

        $business = Business::factory()->create();
        $this->resource = Resource::factory()->create([
            'business_id' => $business->id,
            'code' => 'PC-05',
            'name' => 'PC-05',
        ]);
        $this->customer = User::factory()->create();
    }

    public function test_confirmed_reservation_blocks_overlapping_intervals(): void
    {
        $this->createReservation('2026-10-01 18:00:00+00', '2026-10-01 21:00:00+00', ReservationStatus::Confirmed);

        $this->assertPostgresExclusionViolation(fn () => $this->createReservation(
            '2026-10-01 19:00:00+00',
            '2026-10-01 22:00:00+00',
            ReservationStatus::Confirmed,
        ));

        $this->assertPostgresExclusionViolation(fn () => $this->createReservation(
            '2026-10-01 17:00:00+00',
            '2026-10-01 19:30:00+00',
            ReservationStatus::Confirmed,
        ));

        $this->assertPostgresExclusionViolation(fn () => $this->createReservation(
            '2026-10-01 18:30:00+00',
            '2026-10-01 20:00:00+00',
            ReservationStatus::Confirmed,
        ));
    }

    public function test_back_to_back_reservations_are_allowed(): void
    {
        $this->createReservation('2026-10-01 18:00:00+00', '2026-10-01 21:00:00+00', ReservationStatus::Confirmed);

        $this->createReservation('2026-10-01 21:00:00+00', '2026-10-01 23:00:00+00', ReservationStatus::Confirmed);

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_cancelled_reservation_does_not_block_new_booking(): void
    {
        $this->createReservation('2026-10-01 18:00:00+00', '2026-10-01 21:00:00+00', ReservationStatus::Cancelled);

        $this->createReservation('2026-10-01 19:00:00+00', '2026-10-01 22:00:00+00', ReservationStatus::Confirmed);

        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_pending_and_checked_in_statuses_also_occupy_resource(): void
    {
        $this->createReservation('2026-10-02 18:00:00+00', '2026-10-02 21:00:00+00', ReservationStatus::Pending);

        $this->assertPostgresExclusionViolation(fn () => $this->createReservation(
            '2026-10-02 19:00:00+00',
            '2026-10-02 22:00:00+00',
            ReservationStatus::Confirmed,
        ));

        $this->createReservation('2026-10-03 18:00:00+00', '2026-10-03 21:00:00+00', ReservationStatus::CheckedIn);

        $this->assertPostgresExclusionViolation(fn () => $this->createReservation(
            '2026-10-03 20:00:00+00',
            '2026-10-03 22:00:00+00',
            ReservationStatus::Confirmed,
        ));
    }

    private function createReservation(string $start, string $end, ReservationStatus $status): void
    {
        $startAt = CarbonImmutable::parse($start)->utc();
        $endAt = CarbonImmutable::parse($end)->utc();
        $durationMinutes = (int) $startAt->diffInMinutes($endAt);

        \App\Models\Reservation::query()->create([
            'customer_id' => $this->customer->id,
            'business_id' => $this->resource->business_id,
            'resource_id' => $this->resource->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'duration_minutes' => $durationMinutes,
            'buffer_minutes_applied' => 0,
            'status' => $status,
            'hourly_rate_amount' => 20_000,
            'subtotal_amount' => (int) (20_000 * $durationMinutes / 60),
            'discount_amount' => 0,
            'total_amount' => (int) (20_000 * $durationMinutes / 60),
            'currency' => 'UZS',
            'confirmation_mode' => 'instant',
            'payment_status' => 'pay_at_venue',
            'payment_method' => 'venue',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }
}
