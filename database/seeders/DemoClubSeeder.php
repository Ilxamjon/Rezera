<?php

namespace Database\Seeders;

use App\Actions\Businesses\UpdateBusinessWorkingHoursAction;
use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Businesses\Enums\OnboardingStatus;
use App\Domain\Identity\Enums\Locale;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Domain\Resources\Enums\ResourceType;
use App\Models\BookingPolicy;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessMember;
use App\Models\BusinessSubscription;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\User;
use App\Services\Businesses\BusinessOnboardingService;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent demo data for Tashkent onboarding / local device testing.
 *
 * Credentials (password for all: "password"):
 * - Owner:    +998901000001  (Neon Arena)
 * - Staff:    +998901000002
 * - Customer: +998901000003
 * - Owner 2:  +998901000004  (Pixel Hub, manual confirm)
 * - Admin:    +998901000009
 */
class DemoClubSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $this->call([
            BusinessCategorySeeder::class,
            SubscriptionPlanSeeder::class,
            PlatformSettingSeeder::class,
        ]);

        $gaming = BusinessCategory::query()->where('slug', 'gaming_club')->firstOrFail();
        $password = Hash::make(self::DEMO_PASSWORD);

        $owner = $this->user('+998901000001', 'Neon Arena Owner', $password);
        $staff = $this->user('+998901000002', 'Neon Arena Staff', $password);
        $customer = $this->user('+998901000003', 'Demo Customer', $password);
        $owner2 = $this->user('+998901000004', 'Pixel Hub Owner', $password);
        $this->user('+998901000009', 'Platform Admin', $password, PlatformRole::PlatformAdmin);

        unset($customer);

        $neon = $this->business(
            owner: $owner,
            category: $gaming,
            name: 'Neon Arena',
            email: 'neon-arena@demo.rezera.local',
            phone: '+998712000001',
            district: 'Yunusobod',
            address: 'Amir Temur ko‘chasi 108',
            latitude: 41.367500,
            longitude: 69.287800,
            confirmationMode: ConfirmationMode::Instant,
            description: 'Toshkent Yunusobod — VIP va oddiy zal, RTX 4070 / 4080 PC lar.',
        );

        BusinessMember::query()->updateOrCreate(
            ['business_id' => $neon->id, 'user_id' => $staff->id],
            [
                'member_role' => BusinessMemberRole::Staff,
                'status' => BusinessMemberStatus::Active,
                'joined_at' => now(),
            ],
        );

        $this->seedNeonResources($neon);
        $this->overnightHours($neon);
        $this->finalizeOnboarding($neon);

        $pixel = $this->business(
            owner: $owner2,
            category: $gaming,
            name: 'Pixel Hub',
            email: 'pixel-hub@demo.rezera.local',
            phone: '+998712000002',
            district: 'Chilonzor',
            address: 'Bunyodkor ko‘chasi 15',
            latitude: 41.285600,
            longitude: 69.203400,
            confirmationMode: ConfirmationMode::Manual,
            description: 'Chilonzor — qo‘lda tasdiqlanadigan bronlar, 24/7 zal.',
        );

        $this->seedPixelResources($pixel);
        $this->alwaysOpenHours($pixel);
        $this->finalizeOnboarding($pixel);

        $this->command?->info('Demo clubs ready. Login phone +998901000001 / password (owner).');
    }

    private function user(
        string $phone,
        string $name,
        string $passwordHash,
        PlatformRole $role = PlatformRole::User,
    ): User {
        return User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $name,
                'email' => str_replace('+', '', $phone).'@demo.rezera.local',
                'password' => $passwordHash,
                'locale' => Locale::Russian,
                'platform_role' => $role,
                'status' => UserStatus::Active,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ],
        );
    }

    private function business(
        User $owner,
        BusinessCategory $category,
        string $name,
        string $email,
        string $phone,
        string $district,
        string $address,
        float $latitude,
        float $longitude,
        ConfirmationMode $confirmationMode,
        string $description,
    ): Business {
        $business = Business::query()->updateOrCreate(
            ['email' => $email],
            [
                'category_id' => $category->id,
                'created_by_user_id' => $owner->id,
                'status' => BusinessStatus::Approved,
                'is_publicly_listed' => true,
                'verification_status' => BusinessVerificationStatus::Verified,
                'onboarding_status' => OnboardingStatus::Completed,
                'onboarding_completed_at' => now(),
                'name' => $name,
                'description' => $description,
                'phone' => $phone,
                'country_code' => 'UZ',
                'region' => 'Toshkent shahri',
                'city' => 'Tashkent',
                'district' => $district,
                'address_line' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timezone' => 'Asia/Tashkent',
                'cover_image_url' => null,
                'submitted_at' => now(),
                'reviewed_at' => now(),
            ],
        );

        BusinessMember::query()->updateOrCreate(
            ['business_id' => $business->id, 'user_id' => $owner->id],
            [
                'member_role' => BusinessMemberRole::Owner,
                'status' => BusinessMemberStatus::Active,
                'joined_at' => now(),
            ],
        );

        BookingPolicy::query()->updateOrCreate(
            ['business_id' => $business->id],
            [
                'confirmation_mode' => $confirmationMode,
                'min_duration_minutes' => 60,
                'max_duration_minutes' => 480,
                'duration_step_minutes' => 60,
                'cancellation_deadline_minutes' => 60,
                'pending_expiry_minutes' => 30,
                'check_in_early_minutes' => 15,
                'no_show_grace_minutes' => 20,
                'buffer_minutes' => 0,
                'min_advance_minutes' => 0,
                'max_advance_days' => 14,
                'customer_can_cancel' => true,
                'business_can_cancel' => true,
                'allow_same_day_reservations' => true,
            ],
        );

        try {
            $hasPlan = BusinessSubscription::query()
                ->where('business_id', $business->id)
                ->effective()
                ->exists();

            if (! $hasPlan) {
                app(SubscriptionLifecycleService::class)->assignDefaultPlan($business, $owner);
            }
        } catch (\Throwable) {
            // Plans may already be assigned; ignore on re-seed.
        }

        return $business->fresh();
    }

    private function finalizeOnboarding(Business $business): void
    {
        $synced = app(BusinessOnboardingService::class)->sync($business->fresh());

        if ($synced->onboarding_status !== OnboardingStatus::Completed) {
            throw new \RuntimeException(
                'Demo club onboarding incomplete for '.$business->name.
                ' (status='.$synced->onboarding_status?->value.')'
            );
        }
    }

    private function overnightHours(Business $business): void
    {
        $hours = [];
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $hours[] = [
                'weekday' => $weekday,
                'is_closed' => false,
                'is_open_24h' => false,
                'opens_at' => '12:00',
                'closes_at' => '06:00',
            ];
        }

        app(UpdateBusinessWorkingHoursAction::class)->execute($business, $hours);
    }

    private function alwaysOpenHours(Business $business): void
    {
        $hours = [];
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $hours[] = [
                'weekday' => $weekday,
                'is_closed' => false,
                'is_open_24h' => true,
                'opens_at' => null,
                'closes_at' => null,
            ];
        }

        app(UpdateBusinessWorkingHoursAction::class)->execute($business, $hours);
    }

    private function seedNeonResources(Business $business): void
    {
        $regular = ResourceGroup::query()->updateOrCreate(
            ['business_id' => $business->id, 'name' => 'Regular'],
            [
                'description' => 'Asosiy zal',
                'icon' => 'gaming',
                'color' => '#2F6F6A',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        $vip = ResourceGroup::query()->updateOrCreate(
            ['business_id' => $business->id, 'name' => 'VIP'],
            [
                'description' => 'VIP kabinalar',
                'icon' => 'gaming',
                'color' => '#E8A317',
                'sort_order' => 2,
                'is_active' => true,
            ],
        );

        for ($i = 1; $i <= 6; $i++) {
            $code = 'PC-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $this->pc($business, $regular->id, $code, $code, 20_000, [
                'cpu' => 'Ryzen 5 5600',
                'gpu' => 'RTX 4060',
                'ram_gb' => 16,
                'monitor_hz' => 144,
            ], $i);
        }

        for ($i = 1; $i <= 2; $i++) {
            $code = 'VIP-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $this->pc($business, $vip->id, $code, $code, 45_000, [
                'cpu' => 'Ryzen 7 7800X3D',
                'gpu' => 'RTX 4080',
                'ram_gb' => 32,
                'monitor_hz' => 240,
                'peripherals' => 'HyperX + Logitech',
            ], 10 + $i);
        }
    }

    private function seedPixelResources(Business $business): void
    {
        $main = ResourceGroup::query()->updateOrCreate(
            ['business_id' => $business->id, 'name' => 'Main hall'],
            [
                'description' => 'Asosiy zal',
                'icon' => 'gaming',
                'color' => '#14181F',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );

        for ($i = 1; $i <= 4; $i++) {
            $code = 'PX-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $this->pc($business, $main->id, $code, $code, 18_000, [
                'cpu' => 'i5-12400F',
                'gpu' => 'RTX 3060',
                'ram_gb' => 16,
                'monitor_hz' => 144,
            ], $i);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function pc(
        Business $business,
        string $groupId,
        string $code,
        string $name,
        int $hourlyRate,
        array $metadata,
        int $sortOrder,
    ): void {
        Resource::query()->updateOrCreate(
            ['business_id' => $business->id, 'code' => $code],
            [
                'resource_group_id' => $groupId,
                'name' => $name,
                'description' => null,
                'image_url' => null,
                'resource_type' => ResourceType::Pc,
                'status' => ResourceStatus::Active,
                'capacity' => 1,
                'hourly_rate_amount' => $hourlyRate,
                'currency' => 'UZS',
                'rate_unit' => 'hour',
                'metadata' => $metadata,
                'sort_order' => $sortOrder,
            ],
        );
    }
}
