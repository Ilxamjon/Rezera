<?php

namespace App\Actions\Subscriptions;

use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Exceptions\Subscriptions\SubscriptionException;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Payment;
use App\Models\User;
use App\Services\Subscriptions\BusinessEntitlementService;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use App\Services\Subscriptions\SubscriptionPaymentService;
use App\Services\Subscriptions\SubscriptionPlanResolver;

final class SubscribeBusinessAction
{
    public function __construct(
        private readonly SubscriptionPlanResolver $planResolver,
        private readonly SubscriptionLifecycleService $lifecycle,
        private readonly SubscriptionPaymentService $subscriptionPayment,
        private readonly BusinessEntitlementService $entitlements,
    ) {}

    /**
     * @return array{subscription: BusinessSubscription|null, payment: Payment|null, requires_payment: bool}
     */
    public function execute(Business $business, User $actor, string $planCode, string $billingInterval): array
    {
        $plan = $this->planResolver->findPublicByCode($planCode);

        if ($plan === null) {
            throw SubscriptionException::planNotAvailable($planCode);
        }

        $interval = BillingInterval::from($billingInterval);
        $price = $plan->priceForInterval($interval->value);
        $current = $this->entitlements->effectiveSubscription($business)?->load('plan');

        if ($current !== null && $current->plan_id === $plan->id && $current->billing_interval === $interval) {
            return [
                'subscription' => $current->load(['plan.entitlements']),
                'payment' => null,
                'requires_payment' => false,
            ];
        }

        if ($price === 0) {
            $subscription = $this->lifecycle->createSubscription(
                business: $business,
                plan: $plan,
                interval: $interval,
                status: SubscriptionStatus::Active,
                actor: $actor,
            );

            return [
                'subscription' => $subscription,
                'payment' => null,
                'requires_payment' => false,
            ];
        }

        $trialEligible = config('subscriptions.trial_enabled', true)
            && $plan->trial_days > 0
            && ($current === null || $current->plan?->code === (string) config('subscriptions.default_plan', 'free'));

        if ($trialEligible) {
            $subscription = $this->lifecycle->createSubscription(
                business: $business,
                plan: $plan,
                interval: $interval,
                status: SubscriptionStatus::Trialing,
                actor: $actor,
                trial: true,
            );

            return [
                'subscription' => $subscription,
                'payment' => null,
                'requires_payment' => false,
            ];
        }

        $isUpgrade = $current === null
            || $plan->sort_order > ($current->plan?->sort_order ?? 0);

        if (! $isUpgrade && $current !== null) {
            $this->lifecycle->changePlan($current, $plan, $interval, immediate: false, actor: $actor);

            return [
                'subscription' => $current->fresh(['plan.entitlements', 'pendingPlan']),
                'payment' => null,
                'requires_payment' => false,
            ];
        }

        $payment = $this->subscriptionPayment->createSubscriptionPayment(
            payer: $actor,
            business: $business,
            plan: $plan,
            interval: $interval,
            intent: [
                'action' => $current === null ? 'create' : 'upgrade',
                'previous_subscription_id' => $current?->id,
            ],
        );

        return [
            'subscription' => $current?->load(['plan.entitlements']),
            'payment' => $payment,
            'requires_payment' => true,
        ];
    }
}
