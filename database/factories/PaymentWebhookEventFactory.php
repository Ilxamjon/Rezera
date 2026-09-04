<?php

namespace Database\Factories;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\WebhookEventStatus;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentWebhookEvent>
 */
class PaymentWebhookEventFactory extends Factory
{
    protected $model = PaymentWebhookEvent::class;

    public function definition(): array
    {
        return [
            'provider' => PaymentProvider::Mock,
            'event_id' => 'evt_'.fake()->unique()->uuid(),
            'payment_id' => Payment::factory(),
            'event_type' => 'payment.status',
            'payload' => ['status' => 'paid'],
            'signature' => null,
            'status' => WebhookEventStatus::Pending,
            'processed_at' => null,
            'error_message' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => WebhookEventStatus::Processed,
            'processed_at' => now(),
        ]);
    }
}
