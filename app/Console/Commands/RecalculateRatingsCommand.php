<?php

namespace App\Console\Commands;

use App\Services\Reviews\RatingSummaryService;
use Illuminate\Console\Command;

class RecalculateRatingsCommand extends Command
{
    protected $signature = 'reviews:recalculate-ratings {business_id? : Optional business UUID}';

    protected $description = 'Recalculate cached business rating aggregates from published reviews';

    public function handle(RatingSummaryService $ratingSummary): int
    {
        $businessId = $this->argument('business_id');
        $count = $ratingSummary->recalculate(is_string($businessId) ? $businessId : null);

        $this->info("Recalculated ratings for {$count} business(es).");

        return self::SUCCESS;
    }
}
