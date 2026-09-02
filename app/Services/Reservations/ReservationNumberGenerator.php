<?php

namespace App\Services\Reservations;

use App\Models\Reservation;
use Illuminate\Support\Str;

final class ReservationNumberGenerator
{
    public function generate(): string
    {
        $datePart = now()->utc()->format('Ymd');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $suffix = str_pad((string) random_int(1, 999_999), 6, '0', STR_PAD_LEFT);
            $number = "RZ-{$datePart}-{$suffix}";

            if (! Reservation::query()->where('reservation_number', $number)->exists()) {
                return $number;
            }
        }

        return 'RZ-'.$datePart.'-'.Str::upper(Str::random(6));
    }
}
