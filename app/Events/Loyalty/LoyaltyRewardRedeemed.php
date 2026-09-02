<?php

namespace App\Events\Loyalty;

use App\Models\LoyaltyRedemption;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LoyaltyRewardRedeemed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly LoyaltyRedemption $redemption,
    ) {}
}
