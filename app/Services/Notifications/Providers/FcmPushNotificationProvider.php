<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\Notifications\PushDeliveryResult;
use App\Contracts\Notifications\PushNotificationProviderInterface;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Notifications\Providers\Fcm\FcmClient;

final class FcmPushNotificationProvider implements PushNotificationProviderInterface
{
    public function __construct(
        private readonly FcmClient $client,
    ) {}

    public function sendToDevice(UserDevice $device, string $title, string $body, array $data = []): PushDeliveryResult
    {
        if (! $device->is_active || $device->push_token === null || $device->push_token === '') {
            return new PushDeliveryResult(false, errorMessage: 'Device inactive or missing token');
        }

        $result = $this->client->sendToToken($device->push_token, $title, $body, $data);

        if ($result->unregistered) {
            $device->update([
                'is_active' => false,
                'push_token' => null,
                'last_seen_at' => now(),
            ]);
        }

        return new PushDeliveryResult(
            $result->success,
            providerMessageId: $result->messageName,
            errorMessage: $result->errorMessage,
        );
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): PushDeliveryResult
    {
        $devices = $user->devices()
            ->where('is_active', true)
            ->whereNotNull('push_token')
            ->get();

        if ($devices->isEmpty()) {
            return new PushDeliveryResult(false, errorMessage: 'No active device');
        }

        $lastSuccess = null;
        $lastFailure = null;

        foreach ($devices as $device) {
            $result = $this->sendToDevice($device, $title, $body, $data);
            if ($result->success) {
                $lastSuccess = $result;
            } else {
                $lastFailure = $result;
            }
        }

        return $lastSuccess ?? $lastFailure ?? new PushDeliveryResult(false, errorMessage: 'No active device');
    }
}
