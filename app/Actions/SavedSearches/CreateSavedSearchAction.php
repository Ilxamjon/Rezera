<?php

namespace App\Actions\SavedSearches;

use App\Domain\SavedSearches\Enums\SavedSearchAlertChannel;
use App\Domain\SavedSearches\Enums\SavedSearchAlertFrequency;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\SavedSearches\SavedSearchValidator;

final class CreateSavedSearchAction
{
    public function __construct(
        private readonly SavedSearchValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): SavedSearch
    {
        $this->validator->assertWithinUserLimit($user);
        $this->validator->validate($data);

        $payload = array_merge([
            'alert_channel' => SavedSearchAlertChannel::InApp->value,
            'alert_frequency' => SavedSearchAlertFrequency::Instant->value,
            'availability_required' => false,
            'alert_enabled' => false,
        ], $data, [
            'user_id' => $user->id,
        ]);

        return SavedSearch::query()->create($payload);
    }
}
