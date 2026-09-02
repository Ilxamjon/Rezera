<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\Notifications\SmsDeliveryResult;
use App\Contracts\Notifications\SmsProviderInterface;
use Illuminate\Support\Str;

final class MockSmsProvider implements SmsProviderInterface
{
  public function sendSms(string $phone, string $message): SmsDeliveryResult
  {
    unset($message);

    if ($phone === '') {
      return new SmsDeliveryResult(false, errorMessage: 'Missing phone');
    }

    return new SmsDeliveryResult(true, providerMessageId: 'mock_sms_'.Str::uuid());
  }
}
