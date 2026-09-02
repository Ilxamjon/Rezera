<?php

namespace App\Services\Subscriptions;

use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Exceptions\Subscriptions\SubscriptionException;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Cache;

final class SubscriptionPlanResolver
{
    public function defaultPlan(): SubscriptionPlan
    {
        $code = (string) config('subscriptions.default_plan', 'free');

        $plan = $this->findByCode($code);

        if ($plan === null || ! $plan->is_active) {
            throw SubscriptionException::defaultPlanMissing($code);
        }

        return $plan;
    }

    public function findByCode(string $code): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()
            ->where('code', $code)
            ->first();
    }

    public function findPublicByCode(string $code): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->where('is_public', true)
            ->first();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SubscriptionPlan>
     */
    public function publicPlans()
    {
        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->with('entitlements')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function entitlementsForPlan(SubscriptionPlan $plan): array
    {
        return Cache::remember(
            'subscription_plan_entitlements:'.$plan->id,
            now()->addMinutes(5),
            function () use ($plan): array {
                $map = [];

                foreach ($plan->entitlements as $entitlement) {
                    $map[$entitlement->feature_code] = $entitlement->parsedValue();
                }

                return $map;
            },
        );
    }

    public function forgetPlanCache(SubscriptionPlan $plan): void
    {
        Cache::forget('subscription_plan_entitlements:'.$plan->id);
    }

    public function minimumPlanCodeForFeature(string $featureCode): ?string
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->with('entitlements')
            ->get();

        foreach ($plans as $plan) {
            foreach ($plan->entitlements as $entitlement) {
                if ($entitlement->feature_code !== $featureCode) {
                    continue;
                }

                $value = $entitlement->parsedValue();

                if ($value === true || (is_int($value) && $value > 0)) {
                    return $plan->code;
                }
            }
        }

        return null;
    }
}
