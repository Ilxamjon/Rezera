<?php

namespace App\Providers;

use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessMember;
use App\Models\BusinessMemberInvitation;
use App\Models\LoyaltyReward;
use App\Models\Payment;
use App\Models\PricingRule;
use App\Models\PromoCode;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\ResourceGroup;
use App\Models\Review;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Tenant-aware route model bindings for nested business routes.
 *
 * When a route includes `{business}`, child parameters are resolved within
 * that business scope to prevent cross-tenant ID enumeration.
 */
class RouteBindingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->bindWhenBusinessPresent('resource', Resource::class);
        $this->bindWhenBusinessPresent('reservation', Reservation::class);
        $this->bindWhenBusinessPresent('payment', Payment::class);
        $this->bindWhenBusinessPresent('promo', PromoCode::class);
        $this->bindWhenBusinessPresent('rule', PricingRule::class);
        $this->bindWhenBusinessPresent('reward', LoyaltyReward::class);
        $this->bindWhenBusinessPresent('member', BusinessMember::class);
        $this->bindWhenBusinessPresent('invitation', BusinessMemberInvitation::class);
        $this->bindWhenBusinessPresent('review', Review::class);

        $this->app['router']->bind('category', function (string $value, Route $route) {
            $business = $route->parameter('business');

            // Custom binders run before implicit {business} binding, so business may still be a raw ID.
            if (is_string($business) && $business !== '') {
                $business = Business::query()->whereKey($business)->first();
            }

            if ($business instanceof Business) {
                return ResourceGroup::query()
                    ->where('business_id', $business->id)
                    ->whereKey($value)
                    ->firstOrFail();
            }

            return BusinessCategory::query()->whereKey($value)->firstOrFail();
        });
    }

    /**
     * @param  class-string  $modelClass
     */
    private function bindWhenBusinessPresent(string $parameter, string $modelClass, string $businessColumn = 'business_id'): void
    {
        $this->app['router']->bind($parameter, function (string $value, Route $route) use ($modelClass, $businessColumn) {
            $business = $route->parameter('business');

            if ($business instanceof Business && $businessColumn !== null) {
                return $modelClass::query()
                    ->where($businessColumn, $business->id)
                    ->whereKey($value)
                    ->firstOrFail();
            }

            return $modelClass::query()->whereKey($value)->firstOrFail();
        });
    }
}
