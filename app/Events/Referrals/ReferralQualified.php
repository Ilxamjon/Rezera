<?php

namespace App\Events\Referrals;

use App\Models\Referral;
use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReferralQualified
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Referral $referral,
        public readonly Reservation $reservation,
    ) {}
}
