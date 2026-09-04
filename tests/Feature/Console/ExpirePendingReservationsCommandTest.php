<?php

namespace Tests\Feature\Console;

use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\BookingPolicy;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\PostgresTestCase;

class ExpirePendingReservationsCommandTest extends PostgresTestCase
{
    protected bool $seedSubscriptionPlans = false;

    public function test_expires_pending_reservation_past_ttl(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-05 12:00:00', 'Asia/Tashkent'));

        $business = Business::factory()->create(['timezone' => 'Asia/Tashkent']);
        $customer = User::factory()->create();
        $resource = Resource::factory()->create(['business_id' => $business->id]);

        BookingPolicy::query()->updateOrCreate(
            ['business_id' => $business->id],
            [
                'confirmation_mode' => ConfirmationMode::Manual,
                'pending_expiry_minutes' => 30,
            ],
        );

        $reservation = Reservation::factory()->forResource($resource)->create([
            'customer_id' => $customer->id,
            'status' => ReservationStatus::Pending,
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('reservations:expire-pending')->assertSuccessful();

        $this->assertSame(
            ReservationStatus::Expired,
            $reservation->fresh()->status,
        );

        CarbonImmutable::setTestNow();
    }
}
