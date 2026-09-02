<?php

namespace App\Support\Money;

use InvalidArgumentException;

/**
 * Integer-safe money helper for UZS and future currencies.
 * Never use floats for monetary calculations.
 */
final class Money
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency = 'UZS',
    ) {
        if ($this->amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }

        if (strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }
    }

    public static function uzs(int $amount): self
    {
        return new self($amount, 'UZS');
    }

    /**
     * Calculate hourly total using integer arithmetic.
     */
    public static function hourlyTotal(int $hourlyRate, int $durationMinutes, string $currency = 'UZS'): self
    {
        if ($durationMinutes <= 0) {
            throw new InvalidArgumentException('Duration must be positive.');
        }

        $total = intdiv($hourlyRate * $durationMinutes, 60);

        return new self($total, $currency);
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
