<?php

namespace App\Services\Subscriptions;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionActivated;
use App\Events\Subscriptions\SubscriptionCancelled;
use App\Events\Subscriptions\SubscriptionCreated;
use App\Events\Subscriptions\SubscriptionExpired;
use App\Events\Subscriptions\SubscriptionPlanChanged;
use App\Events\Subscriptions\SubscriptionRenewed;
use App\Exceptions\Subscriptions\SubscriptionException;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SubscriptionLifecycleService
{
    public function __construct(
        private readonly SubscriptionPlanResolver $planResolver,
        private readonly SubscriptionTransitionService $transitions,
        private readonly CreateAuditLogAction $auditLog,
    ) {}

    public function assignDefaultPlan(Business $business, ?User $actor = null): BusinessSubscription
    {
        $plan = $this->planResolver->defaultPlan();

        return $this->createSubscription(
            business: $business,
            plan: $plan,
            interval: BillingInterval::Monthly,
            status: SubscriptionStatus::Active,
            actor: $actor,
            trial: false,
        );
    }

    public function createSubscription(
        Business $business,
        SubscriptionPlan $plan,
        BillingInterval $interval,
        SubscriptionStatus $status,
        ?User $actor = null,
        bool $trial = false,
        ?array $metadata = null,
    ): BusinessSubscription {
        return DB::transaction(function () use ($business, $plan, $interval, $status, $actor, $trial, $metadata): BusinessSubscription {
            Business::query()->whereKey($business->id)->lockForUpdate()->first();

            $this->endEffectiveSubscriptions($business, SubscriptionStatus::Expired);

            $now = CarbonImmutable::now('UTC');
            $periodEnd = $interval === BillingInterval::Yearly
                ? $now->addYear()
                : $now->addMonth();

            $subscription = BusinessSubscription::query()->create([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'status' => $status,
                'billing_interval' => $interval,
                'started_at' => $now,
                'trial_ends_at' => $trial && $plan->trial_days > 0 ? $now->addDays($plan->trial_days) : null,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
                'metadata' => $metadata,
            ]);

            $this->auditLog->execute(
                action: 'subscription.created',
                entityType: 'business_subscription',
                entityId: $subscription->id,
                actor: $actor,
                newValues: [
                    'business_id' => $business->id,
                    'plan_code' => $plan->code,
                    'status' => $status->value,
                ],
            );

            DB::afterCommit(function () use ($subscription, $status): void {
                SubscriptionCreated::dispatch($subscription);

                if (in_array($status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true)) {
                    SubscriptionActivated::dispatch($subscription);
                }
            });

            return $subscription->fresh(['plan.entitlements']);
        });
    }

    public function changePlan(
        BusinessSubscription $subscription,
        SubscriptionPlan $newPlan,
        BillingInterval $interval,
        bool $immediate,
        ?User $actor = null,
    ): BusinessSubscription {
        return DB::transaction(function () use ($subscription, $newPlan, $interval, $immediate, $actor): BusinessSubscription {
            $subscription = BusinessSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $oldPlanId = $subscription->plan_id;

            if ($immediate) {
                $subscription->update([
                    'plan_id' => $newPlan->id,
                    'billing_interval' => $interval,
                    'pending_plan_id' => null,
                ]);
            } else {
                $subscription->update([
                    'pending_plan_id' => $newPlan->id,
                    'billing_interval' => $interval,
                ]);
            }

            $fresh = $subscription->fresh(['plan.entitlements', 'pendingPlan']);

            $this->auditLog->execute(
                action: 'subscription.plan_changed',
                entityType: 'business_subscription',
                entityId: $fresh->id,
                actor: $actor,
                oldValues: ['plan_id' => $oldPlanId],
                newValues: [
                    'plan_id' => $immediate ? $newPlan->id : $oldPlanId,
                    'pending_plan_id' => $immediate ? null : $newPlan->id,
                    'immediate' => $immediate,
                ],
            );

            DB::afterCommit(fn () => SubscriptionPlanChanged::dispatch($fresh, $oldPlanId, $immediate));

            return $fresh;
        });
    }

    public function cancel(BusinessSubscription $subscription, bool $atPeriodEnd, ?string $reason = null, ?User $actor = null): BusinessSubscription
    {
        return DB::transaction(function () use ($subscription, $atPeriodEnd, $reason, $actor): BusinessSubscription {
            $subscription = BusinessSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($atPeriodEnd) {
                $subscription->update([
                    'cancel_at_period_end' => $subscription->current_period_end ?? now(),
                    'cancellation_reason' => $reason,
                ]);
            } else {
                $this->transitions->assertCanTransition($subscription->status, SubscriptionStatus::Cancelled);
                $subscription->update([
                    'status' => SubscriptionStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                    'ended_at' => now(),
                ]);
            }

            $fresh = $subscription->fresh(['plan.entitlements']);

            $this->auditLog->execute(
                action: 'subscription.cancelled',
                entityType: 'business_subscription',
                entityId: $fresh->id,
                actor: $actor,
                newValues: [
                    'at_period_end' => $atPeriodEnd,
                    'reason' => $reason,
                ],
            );

            DB::afterCommit(fn () => SubscriptionCancelled::dispatch($fresh, $atPeriodEnd));

            return $fresh;
        });
    }

    public function resume(BusinessSubscription $subscription, ?User $actor = null): BusinessSubscription
    {
        return DB::transaction(function () use ($subscription, $actor): BusinessSubscription {
            $subscription = BusinessSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if (! $subscription->isScheduledForCancellation()) {
                throw SubscriptionException::invalidTransition($subscription->status->value, 'resume');
            }

            $subscription->update([
                'cancel_at_period_end' => null,
                'cancellation_reason' => null,
            ]);

            $fresh = $subscription->fresh(['plan.entitlements']);

            $this->auditLog->execute(
                action: 'subscription.resumed',
                entityType: 'business_subscription',
                entityId: $fresh->id,
                actor: $actor,
            );

            return $fresh;
        });
    }

    public function transitionStatus(BusinessSubscription $subscription, SubscriptionStatus $to): BusinessSubscription
    {
        return DB::transaction(function () use ($subscription, $to): BusinessSubscription {
            $subscription = BusinessSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $from = $subscription->status;

            if ($from === null) {
                throw SubscriptionException::invalidTransition('unknown', $to->value);
            }

            $this->transitions->assertCanTransition($from, $to);

            $updates = ['status' => $to];

            if ($to === SubscriptionStatus::Cancelled) {
                $updates['cancelled_at'] = now();
                $updates['ended_at'] = now();
            }

            if ($to === SubscriptionStatus::Expired) {
                $updates['ended_at'] = now();
            }

            if ($to === SubscriptionStatus::Active && $from === SubscriptionStatus::Trialing) {
                $updates['trial_ends_at'] = now();
            }

            $subscription->update($updates);
            $fresh = $subscription->fresh(['plan.entitlements']);

            DB::afterCommit(function () use ($fresh, $from, $to): void {
                if ($to === SubscriptionStatus::Active) {
                    SubscriptionActivated::dispatch($fresh);
                }

                if ($to === SubscriptionStatus::Expired) {
                    SubscriptionExpired::dispatch($fresh);
                }

                if ($to === SubscriptionStatus::Active && $from === SubscriptionStatus::PastDue) {
                    SubscriptionRenewed::dispatch($fresh);
                }
            });

            return $fresh;
        });
    }

    public function renewPeriod(BusinessSubscription $subscription): BusinessSubscription
    {
        return DB::transaction(function () use ($subscription): BusinessSubscription {
            $subscription = BusinessSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $start = CarbonImmutable::now('UTC');
            $end = $subscription->billing_interval === BillingInterval::Yearly
                ? $start->addYear()
                : $start->addMonth();

            $subscription->update([
                'current_period_start' => $start,
                'current_period_end' => $end,
            ]);

            $fresh = $subscription->fresh(['plan.entitlements']);

            DB::afterCommit(fn () => SubscriptionRenewed::dispatch($fresh));

            return $fresh;
        });
    }

    public function applyPendingPlanChange(BusinessSubscription $subscription): BusinessSubscription
    {
        if ($subscription->pending_plan_id === null) {
            return $subscription;
        }

        return DB::transaction(function () use ($subscription): BusinessSubscription {
            $subscription = BusinessSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($subscription->pending_plan_id === null) {
                return $subscription;
            }

            $subscription->update([
                'plan_id' => $subscription->pending_plan_id,
                'pending_plan_id' => null,
            ]);

            return $subscription->fresh(['plan.entitlements', 'pendingPlan']);
        });
    }

    private function endEffectiveSubscriptions(Business $business, SubscriptionStatus $status): void
    {
        BusinessSubscription::query()
            ->where('business_id', $business->id)
            ->effective()
            ->update([
                'status' => $status,
                'ended_at' => now(),
            ]);
    }
}
