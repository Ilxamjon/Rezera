<?php

namespace App\Providers;

use App\Domain\Identity\Enums\PlatformRole;
use App\Models\Business;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyReward;
use App\Models\Payment;
use App\Models\UserDevice;
use App\Models\UserNotification;
use App\Models\Reservation;
use App\Models\PricingRule;
use App\Models\PromoCode;
use App\Models\Referral;
use App\Models\Review;
use App\Models\SavedSearch;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\User;
use App\Policies\BusinessPolicy;
use App\Policies\LoyaltyPolicy;
use App\Policies\LoyaltyRewardPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReferralPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\PricingRulePolicy;
use App\Policies\PromoCodePolicy;
use App\Policies\ReviewPolicy;
use App\Policies\SavedSearchPolicy;
use App\Policies\UserDevicePolicy;
use App\Policies\UserNotificationPolicy;
use App\Policies\ResourceGroupPolicy;
use App\Policies\ResourcePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Business::class => BusinessPolicy::class,
        ResourceGroup::class => ResourceGroupPolicy::class,
        Resource::class => ResourcePolicy::class,
        Reservation::class => ReservationPolicy::class,
        Review::class => ReviewPolicy::class,
        SavedSearch::class => SavedSearchPolicy::class,
        PromoCode::class => PromoCodePolicy::class,
        LoyaltyProgram::class => LoyaltyPolicy::class,
        LoyaltyReward::class => LoyaltyRewardPolicy::class,
        Referral::class => ReferralPolicy::class,
        PricingRule::class => PricingRulePolicy::class,
        Payment::class => PaymentPolicy::class,
        UserNotification::class => UserNotificationPolicy::class,
        UserDevice::class => UserDevicePolicy::class,
    ];

    public function boot(): void
    {
        $this->registerGates();
    }

    protected function registerGates(): void
    {
        Gate::define('platform-admin', static function (User $user): bool {
            return $user->platform_role?->isStaff() === true;
        });
    }
}
