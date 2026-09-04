<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notifications\NotificationChannelResolver;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\Providers\Fcm\FcmClient;
use App\Services\Notifications\Providers\FcmPushNotificationProvider;
use App\Services\Notifications\Providers\MockPushNotificationProvider;
use Illuminate\Support\Facades\Http;
use Tests\PostgresTestCase;

class FcmPushNotificationProviderTest extends PostgresTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.channels.push.enabled' => true,
            'notifications.providers.push.driver' => 'fcm',
            'notifications.fcm.project_id' => 'rezera-test',
            'notifications.fcm.credentials_path' => null,
            'notifications.fcm.credentials_json' => json_encode([
                'type' => 'service_account',
                'project_id' => 'rezera-test',
                'client_email' => 'firebase-adminsdk@rezera-test.iam.gserviceaccount.com',
            ], JSON_THROW_ON_ERROR),
            'notifications.fcm.access_token' => 'ya29.test-token',
        ]);
    }

    public function test_resolver_uses_fcm_driver(): void
    {
        $this->assertInstanceOf(
            FcmPushNotificationProvider::class,
            app(NotificationChannelResolver::class)->pushProvider(),
        );

        config(['notifications.providers.push.driver' => 'mock']);

        $this->assertInstanceOf(
            MockPushNotificationProvider::class,
            app(NotificationChannelResolver::class)->pushProvider(),
        );
    }

    public function test_fcm_client_stringifies_mixed_data_values(): void
    {
        $data = app(FcmClient::class)->stringifyData([
            'reservation_id' => 'abc',
            'count' => 3,
            'ok' => true,
            'nested' => ['a' => 1],
            0 => 'skip-numeric-key',
        ]);

        $this->assertSame('abc', $data['reservation_id']);
        $this->assertSame('3', $data['count']);
        $this->assertSame('true', $data['ok']);
        $this->assertSame('{"a":1}', $data['nested']);
        $this->assertArrayNotHasKey(0, $data);
    }

    public function test_sends_confirm_push_to_all_active_devices(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/rezera-test/messages/1',
            ]),
        ]);

        $user = User::factory()->create();
        UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'phone-a',
            'push_token' => 'token-a',
        ]);
        UserDevice::factory()->create([
            'user_id' => $user->id,
            'device_id' => 'phone-b',
            'push_token' => 'token-b',
        ]);

        $result = app(FcmPushNotificationProvider::class)->sendToUser(
            $user,
            'Booking confirmed',
            'RZ-1 is confirmed',
            ['type' => 'reservation_confirmed', 'reservation_id' => 'res-1'],
        );

        $this->assertTrue($result->success);
        $this->assertSame('projects/rezera-test/messages/1', $result->providerMessageId);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'fcm.googleapis.com');
        });

        $tokens = [];
        Http::assertSent(function ($request) use (&$tokens): bool {
            if (! str_contains($request->url(), 'fcm.googleapis.com')) {
                return false;
            }

            $tokens[] = $request['message']['token'] ?? null;
            $this->assertSame('Bearer ya29.test-token', $request->header('Authorization')[0] ?? null);
            $this->assertSame('Booking confirmed', $request['message']['notification']['title']);
            $this->assertSame('reservation_confirmed', $request['message']['data']['type']);

            return true;
        });

        $this->assertEqualsCanonicalizing(['token-a', 'token-b'], $tokens);
    }

    public function test_unregistered_token_deactivates_device(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'Requested entity was not found.',
                    'status' => 'NOT_FOUND',
                    'details' => [[
                        '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                        'errorCode' => 'UNREGISTERED',
                    ]],
                ],
            ], 404),
        ]);

        $device = UserDevice::factory()->create(['push_token' => 'stale-token']);

        $result = app(FcmPushNotificationProvider::class)->sendToDevice(
            $device,
            'Title',
            'Body',
        );

        $this->assertFalse($result->success);
        $this->assertFalse($device->fresh()->is_active);
        $this->assertNull($device->fresh()->push_token);
    }

    public function test_missing_credentials_do_not_call_fcm(): void
    {
        Http::fake();
        config([
            'notifications.fcm.project_id' => null,
            'notifications.fcm.credentials_path' => storage_path('app/missing-firebase.json'),
            'notifications.fcm.credentials_json' => null,
            'notifications.fcm.access_token' => null,
        ]);

        $device = UserDevice::factory()->create(['push_token' => 'token-x']);
        $result = app(FcmPushNotificationProvider::class)->sendToDevice($device, 'T', 'B');

        $this->assertFalse($result->success);
        $this->assertSame('FCM credentials are not configured', $result->errorMessage);
        Http::assertNothingSent();
    }

    public function test_reservation_confirmed_dispatches_fcm_when_push_enabled(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/rezera-test/messages/confirm',
            ]),
        ]);

        $user = User::factory()->create();
        UserDevice::factory()->create([
            'user_id' => $user->id,
            'push_token' => 'confirm-token',
        ]);

        app(NotificationService::class)->notifyUser(
            user: $user,
            type: NotificationType::ReservationConfirmed,
            entityKey: 'reservation:fcm-confirm',
            placeholders: [
                'reservation_number' => 'RZ-FCM',
                'business_name' => 'Test Club',
                'resource_name' => 'PC-1',
                'date' => '2026-09-05',
                'start_time' => '10:00',
                'end_time' => '12:00',
                'amount' => '60000',
                'currency' => 'UZS',
            ],
            data: ['reservation_id' => 'res-fcm'],
            channels: [NotificationChannel::Database, NotificationChannel::Push],
        );

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'fcm.googleapis.com')
                && ($request['message']['token'] ?? null) === 'confirm-token';
        });
    }
}
