<?php

namespace App\Services\Referrals;

use App\Models\ReferralCode;
use Illuminate\Support\Str;

final class ReferralCodeGenerator
{
    public function generate(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (ReferralCode::query()->where('code', $code)->exists());

        return $code;
    }
}
