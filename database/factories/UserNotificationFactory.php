<?php

namespace Database\Factories;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserNotification>
 */
class UserNotificationFactory extends Factory
{
    protected $model = UserNotification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => NotificationType::ReservationConfirmed,
            'channel' => NotificationChannel::Database,
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(),
            'data' => ['type' => 'reservation'],
            'status' => 'sent',
            'idempotency_key' => fake()->unique()->sha256(),
            'read_at' => null,
            'sent_at' => now(),
            'failed_at' => null,
        ];
    }
}
