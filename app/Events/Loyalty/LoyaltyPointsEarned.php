<?php

namespace App\Events\Loyalty;

use App\Models\LoyaltyTransaction;
use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LoyaltyPointsEarned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly LoyaltyTransaction $transaction,
        public readonly Reservation $reservation,
    ) {}
}
