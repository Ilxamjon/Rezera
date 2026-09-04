<?php

namespace App\Services\Notifications\Providers\Fcm;

final class FcmSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageName = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $unregistered = false,
    ) {}
}
