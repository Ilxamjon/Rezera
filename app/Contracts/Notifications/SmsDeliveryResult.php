<?php

namespace App\Contracts\Notifications;

final class SmsDeliveryResult
{
  public function __construct(
    public readonly bool $success,
    public readonly ?string $providerMessageId = null,
    public readonly ?string $errorMessage = null,
  ) {}
}
