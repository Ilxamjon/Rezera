<?php

namespace App\Contracts\Notifications;

interface SmsProviderInterface
{
  public function sendSms(string $phone, string $message): SmsDeliveryResult;
}
