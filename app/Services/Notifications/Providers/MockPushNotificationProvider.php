<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\Notifications\PushDeliveryResult;
use App\Contracts\Notifications\PushNotificationProviderInterface;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Support\Str;

final class MockPushNotificationProvider implements PushNotificationProviderInterface
{
  public function sendToDevice(UserDevice $device, string $title, string $body, array $data = []): PushDeliveryResult
  {
    unset($title, $body, $data);

    if (! $device->is_active || $device->push_token === null) {
      return new PushDeliveryResult(false, errorMessage: 'Device inactive or missing token');
    }

    return new PushDeliveryResult(true, providerMessageId: 'mock_push_'.Str::uuid());
  }

  public function sendToUser(User $user, string $title, string $body, array $data = []): PushDeliveryResult
  {
    $device = $user->devices()->where('is_active', true)->whereNotNull('push_token')->first();

    if ($device === null) {
      return new PushDeliveryResult(false, errorMessage: 'No active device');
    }

    return $this->sendToDevice($device, $title, $body, $data);
  }
}
