<?php

namespace App\Services\Payments;

use App\Models\Payment;

final class PaymentNumberGenerator
{
    public function generate(): string
    {
        $datePart = now()->utc()->format('Ymd');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $suffix = str_pad((string) random_int(1, 999_999), 6, '0', STR_PAD_LEFT);
            $number = "RZ-PAY-{$datePart}-{$suffix}";

            if (! Payment::query()->where('payment_number', $number)->exists()) {
                return $number;
            }
        }

        return 'RZ-PAY-'.$datePart.'-'.strtoupper(substr(uniqid('', true), -6));
    }
}
