<?php

namespace Tests\Feature\Api\V1\Notification;

use App\Domain\Identity\Enums\Locale;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Jobs\Notifications\DeliverNotificationJob;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserNotification;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Queue;
use Tests\PostgresTestCase;
use Tests\Support\AuthenticatesUsers;

class NotificationApiTest extends PostgresTestCase
{
    use AuthenticatesUsers;

    public function test_user_can_list_and_filter_notifications(): void
    {
        $user = User::factory()->create();
        UserNotification::factory()->count(2)->create(['user_id' => $user->id, 'read_at' => null]);
        UserNotification::factory()->create(['user_id' => $user->id, 'read_at' => now()]);

        $this->getJson('/api/v1/me/notifications?unread=1', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonCount(2, 'data.items');

        $this->getJson('/api/v1/me/notifications/unread-count', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $notification = UserNotification::factory()->create(['user_id' => $owner->id]);

        $this->getJson('/api/v1/me/notifications/'.$notification->id, $this->authHeaders($intruder))
            ->assertForbidden();
    }

    public function test_mark_read_is_idempotent(): void
    {
        $user = User::factory()->create();
        $notification = UserNotification::factory()->create(['user_id' => $user->id, 'read_at' => null]);

        $this->patchJson('/api/v1/me/notifications/'.$notification->id.'/read', [], $this->authHeaders($user))
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
        $this->patchJson('/api/v1/me/notifications/'.$notification->id.'/read', [], $this->authHeaders($user))
            ->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_updates_in_bulk(): void
    {
        $user = User::factory()->create();
        UserNotification::factory()->count(3)->create(['user_id' => $user->id, 'read_at' => null]);

        $this->postJson('/api/v1/me/notifications/read-all', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.updated', 3);
    }

    public function test_user_can_register_and_deactivate_device(): void
    {
        $user = User::factory()->create();

        $create = $this->postJson('/api/v1/me/devices', [
            'device_id' => 'device-1',
            'platform' => 'android',
            'push_token' => 'token-abc',
            'app_version' => '1.0.0',
        ], $this->authHeaders($user))->assertCreated();

        $deviceId = $create->json('data.id');

        $this->deleteJson('/api/v1/me/devices/'.$deviceId, [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();

        $this->putJson('/api/v1/me/notification-preferences', [
            'preferences' => [[
                'notification_type' => NotificationType::ReservationConfirmed->value,
                'channel' => NotificationChannel::Push->value,
                'enabled' => false,
            ]],
        ], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.items.0.enabled', false);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'notification_type' => NotificationType::ReservationConfirmed->value,
            'channel' => NotificationChannel::Push->value,
            'is_enabled' => false,
        ]);
    }

    public function test_disabled_push_channel_is_not_dispatched(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        NotificationPreference::factory()->create([
            'user_id' => $user->id,
            'notification_type' => NotificationType::ReservationConfirmed,
            'channel' => NotificationChannel::Push,
            'is_enabled' => false,
        ]);

        app(NotificationService::class)->notifyUser(
            user: $user,
            type: NotificationType::ReservationConfirmed,
            entityKey: 'reservation:test',
            placeholders: [
                'reservation_number' => 'RZ-TEST',
                'business_name' => 'Test Club',
                'resource_name' => 'PC-1',
                'date' => '2026-09-05',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'amount' => '60000',
                'currency' => 'UZS',
            ],
            data: ['reservation_id' => 'test'],
            channels: [NotificationChannel::Database, NotificationChannel::Push],
        );

        Queue::assertNotPushed(DeliverNotificationJob::class);
    }

    public function test_notification_content_is_localized(): void
    {
        $user = User::factory()->create(['locale' => Locale::Uzbek]);

        $notification = app(NotificationService::class)->notifyUser(
            user: $user,
            type: NotificationType::ReservationConfirmed,
            entityKey: 'reservation:loc',
            placeholders: [
                'reservation_number' => 'RZ-LOC',
                'business_name' => 'Club',
                'resource_name' => 'PC',
                'date' => '2026-09-05',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'amount' => '1000',
                'currency' => 'UZS',
            ],
            data: [],
        );

        $this->assertStringContainsString('tasdiqlandi', mb_strtolower($notification->title));
    }

    public function test_duplicate_notification_is_idempotent(): void
    {
        $user = User::factory()->create();
        $service = app(NotificationService::class);
        $args = [
            'user' => $user,
            'type' => NotificationType::PaymentSucceeded,
            'entityKey' => 'payment:1',
            'placeholders' => [
                'payment_number' => 'RZ-PAY-1',
                'reservation_number' => 'RZ-1',
                'business_name' => 'Club',
                'amount' => '1000',
                'currency' => 'UZS',
            ],
            'data' => ['payment_id' => '1'],
        ];

        $first = $service->notifyUser(...$args);
        $second = $service->notifyUser(...$args);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, UserNotification::query()->where('user_id', $user->id)->count());
    }
}
