<?php

namespace App\Domain\Payments\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    /**
     * @return list<self>
     */
    public static function active(): array
    {
        return [
            self::Pending,
            self::Processing,
        ];
    }

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    public function isTerminal(): bool
    {
        return ! $this->isActive();
    }
}
