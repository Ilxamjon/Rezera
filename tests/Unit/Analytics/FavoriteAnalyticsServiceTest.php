<?php

namespace Tests\Unit\Analytics;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\BusinessFavorite;
use App\Models\User;
use App\Services\Analytics\FavoriteAnalyticsService;
use App\Support\Analytics\AnalyticsDateRange;
use Tests\PostgresTestCase;

class FavoriteAnalyticsServiceTest extends PostgresTestCase
{
    public function test_metrics_count_total_and_period_favorites(): void
    {
        $business = Business::factory()->create(['status' => BusinessStatus::Approved]);
        $user = User::factory()->create();

        BusinessFavorite::query()->create([
            'user_id' => $user->id,
            'business_id' => $business->id,
            'created_at' => now()->subDays(2),
        ]);

        BusinessFavorite::query()->create([
            'user_id' => User::factory()->create()->id,
            'business_id' => $business->id,
            'created_at' => now(),
        ]);

        $range = AnalyticsDateRange::preset($business, \App\Domain\Analytics\Enums\AnalyticsDatePreset::Last7Days);
        $metrics = app(FavoriteAnalyticsService::class)->metrics($business, $range);

        $this->assertSame(2, $metrics['total_favorites']);
        $this->assertSame(2, $metrics['favorites_added']);
    }
}
