<?php

namespace Database\Factories;

use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Domain\Reservations\Enums\PaymentMethod;
use App\Domain\Reservations\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        $start = now()->utc()->addDays(fake()->numberBetween(2, 10))->setTime(18, 0, 0);
        $end = $start->copy()->addHours(3);
        $hourlyRate = 20_000;

        return [
            'customer_id' => User::factory(),
            'customer_name_snapshot' => fake()->name(),
            'customer_phone_snapshot' => '+99890'.fake()->numerify('#######'),
            'reservation_number' => 'RZ-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'business_id' => null,
            'resource_id' => Resource::factory(),
            'start_at' => $start,
            'end_at' => $end,
            'duration_minutes' => 180,
            'buffer_minutes_applied' => 0,
            'status' => ReservationStatus::Confirmed,
            'hourly_rate_amount' => $hourlyRate,
            'subtotal_amount' => 60_000,
            'discount_amount' => 0,
            'total_amount' => 60_000,
            'currency' => 'UZS',
            'confirmation_mode' => ConfirmationMode::Instant,
            'payment_status' => PaymentStatus::PayAtVenue,
            'payment_method' => PaymentMethod::Venue,
            'notes' => null,
            'cancellation_reason' => null,
            'rejection_reason' => null,
            'cancelled_by_user_id' => null,
            'cancelled_by_actor_type' => null,
            'checked_in_at' => null,
            'completed_at' => null,
            'expires_at' => null,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Reservation $reservation): void {
            if ($reservation->start_at !== null && $reservation->end_at !== null) {
                $reservation->duration_minutes = (int) $reservation->start_at->diffInMinutes($reservation->end_at);
            }

            if ($reservation->business_id !== null) {
                $resource = $reservation->resource_id !== null
                    ? Resource::query()->find($reservation->resource_id)
                    : null;

                if ($resource === null || $resource->business_id !== $reservation->business_id) {
                    $resource = Resource::factory()->create([
                        'business_id' => $reservation->business_id,
                    ]);
                    $reservation->resource_id = $resource->id;
                }

                return;
            }

            if ($reservation->resource_id !== null) {
                $resource = Resource::query()->find($reservation->resource_id);

                if ($resource !== null) {
                    $reservation->business_id = $resource->business_id;
                }
            }
        });
    }

    public function forResource(Resource $resource): static
    {
        return $this->state(fn () => [
            'resource_id' => $resource->id,
            'business_id' => $resource->business_id,
        ]);
    }

    public function occupying(ReservationStatus $status = ReservationStatus::Confirmed): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => ReservationStatus::Cancelled]);
    }
}
