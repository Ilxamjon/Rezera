<?php

namespace App\Services\Loyalty;

use Illuminate\Support\Str;

final class LoyaltyCodeGenerator
{
    public function redemptionCode(): string
    {
        do {
            $code = 'RZ-'.Str::upper(Str::random(6));
        } while (\App\Models\LoyaltyRedemption::query()->where('redemption_code', $code)->exists());

        return $code;
    }
}
