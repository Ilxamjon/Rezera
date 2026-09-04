<?php

namespace Database\Factories;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'reservation_id' => Reservation::factory(),
            'user_id' => User::factory(),
            'payment_number' => 'RZ-PAY-'.now()->format('Ymd').'-'.fake()->unique()->numerify('######'),
            'provider' => PaymentProvider::Mock,
            'provider_payment_id' => 'mock_'.fake()->uuid(),
            'status' => PaymentStatus::Pending,
            'payment_method' => PaymentProvider::Mock->value,
            'amount' => 60_000,
            'currency' => 'UZS',
            'description' => 'Test payment',
            'metadata' => [],
            'paid_at' => null,
            'failed_at' => null,
            'cancelled_at' => null,
            'refunded_at' => null,
            'failure_reason' => null,
        ];
    }

    public function forReservation(Reservation $reservation): static
    {
        return $this->state(fn () => [
            'business_id' => $reservation->business_id,
            'reservation_id' => $reservation->id,
            'user_id' => $reservation->customer_id,
            'amount' => $reservation->total_amount,
            'currency' => $reservation->currency,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => 'Declined',
        ]);
    }
}
