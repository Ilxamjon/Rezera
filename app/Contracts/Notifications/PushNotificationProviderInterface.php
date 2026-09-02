<?php

namespace App\Contracts\Notifications;

use App\Models\User;
use App\Models\UserDevice;

interface PushNotificationProviderInterface
{
  /**
   * @param  array<string, mixed>  $data
   */
  public function sendToDevice(UserDevice $device, string $title, string $body, array $data = []): PushDeliveryResult;

  /**
   * @param  array<string, mixed>  $data
   */
  public function sendToUser(User $user, string $title, string $body, array $data = []): PushDeliveryResult;
}
