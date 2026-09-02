<?php

namespace App\Console\Commands;

use App\Jobs\SavedSearches\EvaluateSavedSearchJob;
use App\Models\SavedSearch;
use App\Models\SavedSearchAlert;
use App\Services\SavedSearches\SavedSearchMatcher;
use Illuminate\Console\Command;

class ProcessSavedSearchAlertsCommand extends Command
{
    protected $signature = 'saved-searches:process-alerts';

    protected $description = 'Evaluate saved searches with availability alerts enabled';

    public function handle(SavedSearchMatcher $matcher): int
    {
        $batchSize = (int) config('rezera.saved_searches.batch_size', 50);
        $processed = 0;

        SavedSearch::query()
            ->with(['user', 'business'])
            ->tap(fn ($query) => $matcher->scopeEligibleForAlerts($query))
            ->orderBy('last_checked_at')
            ->chunkById($batchSize, function ($searches) use ($matcher, &$processed): void {
                foreach ($searches as $search) {
                    if (! $matcher->isDueForScheduledProcessing($search)) {
                        continue;
                    }

                    EvaluateSavedSearchJob::dispatch($search->id);
                    $processed++;
                }
            });

        $this->info("Dispatched {$processed} saved search evaluation job(s).");

        return self::SUCCESS;
    }
}
