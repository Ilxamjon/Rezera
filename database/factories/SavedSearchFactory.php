<?php

namespace Database\Factories;

use App\Domain\SavedSearches\Enums\SavedSearchAlertChannel;
use App\Domain\SavedSearches\Enums\SavedSearchAlertFrequency;
use App\Domain\SavedSearches\Enums\SavedSearchDateMode;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'search_query' => fake()->optional()->words(2, true),
            'date_mode' => SavedSearchDateMode::NextAvailable,
            'start_time' => '18:00',
            'end_time' => '20:00',
            'availability_required' => true,
            'alert_enabled' => true,
            'alert_channel' => SavedSearchAlertChannel::InApp,
            'alert_frequency' => SavedSearchAlertFrequency::Instant,
        ];
    }

    public function withBusiness(string $businessId): static
    {
        return $this->state(fn () => ['business_id' => $businessId]);
    }
}
