<?php

namespace Tests\Feature\Database;

use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Models\Business;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\DemoClubSeeder;
use Tests\PostgresTestCase;

class DemoClubSeederTest extends PostgresTestCase
{
    protected bool $seedSubscriptionPlans = false;

    public function test_demo_seed_is_idempotent_and_lists_clubs(): void
    {
        config([
            'business_onboarding.public_requires_verification' => true,
            'business_onboarding.public_requires_onboarding_complete' => true,
        ]);

        $this->seed(DemoClubSeeder::class);
        $this->seed(DemoClubSeeder::class);

        $this->assertSame(1, User::query()->where('phone', '+998901000001')->count());
        $this->assertSame(1, Business::query()->where('email', 'neon-arena@demo.rezera.local')->count());
        $this->assertSame(1, Business::query()->where('email', 'pixel-hub@demo.rezera.local')->count());

        $neon = Business::query()->where('email', 'neon-arena@demo.rezera.local')->firstOrFail();
        $pixel = Business::query()->where('email', 'pixel-hub@demo.rezera.local')->firstOrFail();

        $this->assertSame(ConfirmationMode::Instant, $neon->bookingPolicy?->confirmation_mode);
        $this->assertSame(ConfirmationMode::Manual, $pixel->bookingPolicy?->confirmation_mode);
        $this->assertTrue($neon->isPubliclyVisible());
        $this->assertTrue($pixel->isPubliclyVisible());
        $this->assertGreaterThanOrEqual(8, Resource::query()->where('business_id', $neon->id)->count());
        $this->assertGreaterThanOrEqual(4, Resource::query()->where('business_id', $pixel->id)->count());

        $admin = User::query()->where('phone', '+998901000009')->firstOrFail();
        $this->assertSame(PlatformRole::PlatformAdmin, $admin->platform_role);

        $this->getJson('/api/v1/businesses?q=Neon')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Neon Arena']);
    }
}
