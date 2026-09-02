<?php

namespace App\Console\Commands;

use App\Services\Loyalty\LoyaltyService;
use Illuminate\Console\Command;

class ExpireLoyaltyPointsCommand extends Command
{
    protected $signature = 'loyalty:expire-points';

    protected $description = 'Expire loyalty points that have passed their expiration date';

    public function handle(LoyaltyService $loyaltyService): int
    {
        $count = $loyaltyService->expireDuePoints();

        $this->info("Expired {$count} loyalty point batch(es).");

        return self::SUCCESS;
    }
}
