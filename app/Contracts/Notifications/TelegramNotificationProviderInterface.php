<?php

namespace App\Contracts\Notifications;

interface TelegramNotificationProviderInterface
{
  /**
   * @param  array<string, mixed>  $data
   */
  public function sendMessage(string $chatId, string $message, array $data = []): TelegramDeliveryResult;
}
