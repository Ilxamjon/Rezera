<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BusinessCategorySeeder::class,
            SubscriptionPlanSeeder::class,
            PlatformSettingSeeder::class,
        ]);

        $seedDemo = env('SEED_DEMO_CLUB');
        $shouldSeedDemo = $seedDemo === null
            ? app()->environment('local')
            : filter_var($seedDemo, FILTER_VALIDATE_BOOLEAN);

        if ($shouldSeedDemo) {
            $this->call(DemoClubSeeder::class);
        }
    }
}
