<?php

namespace App\Domain\Payments\Enums;

enum PaymentProvider: string
{
    case Mock = 'mock';
    case Payme = 'payme';
    case Click = 'click';
    case Uzum = 'uzum';
    case Stripe = 'stripe';
    case Cash = 'cash';

    public function isImplemented(): bool
    {
        return $this === self::Mock;
    }
}
