<?php

namespace App\Jobs\SavedSearches;

use App\Models\SavedSearch;
use App\Services\SavedSearches\SavedSearchAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class EvaluateSavedSearchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $savedSearchId,
        public readonly bool $ignoreFrequency = false,
    ) {}

    public function handle(SavedSearchAlertService $alertService): void
    {
        $savedSearch = SavedSearch::query()
            ->with(['user', 'business'])
            ->find($this->savedSearchId);

        if ($savedSearch === null) {
            return;
        }

        $alertService->evaluate($savedSearch, $this->ignoreFrequency);
    }
}
