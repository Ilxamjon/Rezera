<?php

namespace App\Actions\Subscriptions;

use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use Illuminate\Support\Facades\DB;

final class FulfillSubscriptionPaymentAction
{
    public function __construct(
        private readonly SubscriptionLifecycleService $lifecycle,
    ) {}

    public function execute(Payment $payment): BusinessSubscription
    {
        $intent = $payment->metadata['subscription_intent'] ?? null;

        if (! is_array($intent)) {
            throw new \InvalidArgumentException('Missing subscription intent.');
        }

        return DB::transaction(function () use ($payment, $intent): BusinessSubscription {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (($payment->metadata['subscription_fulfilled'] ?? false) === true) {
                $existingId = $payment->business_subscription_id;

                if ($existingId !== null) {
                    return BusinessSubscription::query()->findOrFail($existingId);
                }
            }

            $business = Business::query()->findOrFail($intent['business_id'] ?? $payment->business_id);
            $plan = SubscriptionPlan::query()->findOrFail($intent['plan_id']);
            $interval = BillingInterval::from((string) ($intent['billing_interval'] ?? 'monthly'));
            $action = (string) ($intent['action'] ?? 'create');

            if ($action === 'upgrade' && ! empty($intent['previous_subscription_id'])) {
                $current = BusinessSubscription::query()->find($intent['previous_subscription_id']);

                if ($current !== null) {
                    $subscription = $this->lifecycle->changePlan($current, $plan, $interval, immediate: true);
                } else {
                    $subscription = $this->lifecycle->createSubscription(
                        business: $business,
                        plan: $plan,
                        interval: $interval,
                        status: SubscriptionStatus::Active,
                    );
                }
            } else {
                $subscription = $this->lifecycle->createSubscription(
                    business: $business,
                    plan: $plan,
                    interval: $interval,
                    status: SubscriptionStatus::Active,
                );
            }

            $payment->update([
                'business_subscription_id' => $subscription->id,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'subscription_fulfilled' => true,
                ]),
            ]);

            return $subscription->fresh(['plan.entitlements']);
        });
    }
}
