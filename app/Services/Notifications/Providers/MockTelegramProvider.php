<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\Notifications\TelegramDeliveryResult;
use App\Contracts\Notifications\TelegramNotificationProviderInterface;
use Illuminate\Support\Str;

final class MockTelegramProvider implements TelegramNotificationProviderInterface
{
  public function sendMessage(string $chatId, string $message, array $data = []): TelegramDeliveryResult
  {
    unset($message, $data);

    if ($chatId === '') {
      return new TelegramDeliveryResult(false, errorMessage: 'Missing chat id');
    }

    return new TelegramDeliveryResult(true, providerMessageId: 'mock_tg_'.Str::uuid());
  }
}
