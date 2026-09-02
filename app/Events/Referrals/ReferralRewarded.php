<?php

namespace App\Events\Referrals;

use App\Models\Referral;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReferralRewarded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Referral $referral,
    ) {}
}
