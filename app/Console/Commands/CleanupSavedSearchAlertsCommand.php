<?php

namespace App\Console\Commands;

use App\Models\SavedSearchAlert;
use Illuminate\Console\Command;

class CleanupSavedSearchAlertsCommand extends Command
{
    protected $signature = 'saved-searches:cleanup-alerts';

    protected $description = 'Remove old saved search alert delivery records';

    public function handle(): int
    {
        $days = (int) config('rezera.saved_searches.alert_retention_days', 90);
        $cutoff = now()->subDays($days);

        $deleted = SavedSearchAlert::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} old saved search alert record(s).");

        return self::SUCCESS;
    }
}
