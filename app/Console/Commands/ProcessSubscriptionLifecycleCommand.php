<?php

namespace App\Console\Commands;

use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Models\BusinessSubscription;
use App\Services\Subscriptions\SubscriptionLifecycleService;
use Illuminate\Console\Command;

final class ProcessSubscriptionLifecycleCommand extends Command
{
    protected $signature = 'subscriptions:process-lifecycle {--limit=200}';

    protected $description = 'Process subscription trials, expirations, and scheduled plan changes';

    public function handle(SubscriptionLifecycleService $lifecycle): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;

        BusinessSubscription::query()
            ->where('status', SubscriptionStatus::Trialing)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->orderBy('trial_ends_at')
            ->limit($limit)
            ->get()
            ->each(function (BusinessSubscription $subscription) use ($lifecycle, &$processed): void {
                $lifecycle->transitionStatus($subscription, SubscriptionStatus::Active);
                $processed++;
            });

        BusinessSubscription::query()
            ->whereNotNull('cancel_at_period_end')
            ->whereNull('cancelled_at')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->orderBy('current_period_end')
            ->limit($limit)
            ->get()
            ->each(function (BusinessSubscription $subscription) use ($lifecycle, &$processed): void {
                $lifecycle->transitionStatus($subscription, SubscriptionStatus::Cancelled);
                $processed++;
            });

        BusinessSubscription::query()
            ->where('status', SubscriptionStatus::Cancelled)
            ->whereNull('ended_at')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->orderBy('current_period_end')
            ->limit($limit)
            ->get()
            ->each(function (BusinessSubscription $subscription) use ($lifecycle, &$processed): void {
                $lifecycle->transitionStatus($subscription, SubscriptionStatus::Expired);
                $processed++;
            });

        BusinessSubscription::query()
            ->whereNotNull('pending_plan_id')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
            ->orderBy('current_period_end')
            ->limit($limit)
            ->get()
            ->each(function (BusinessSubscription $subscription) use ($lifecycle, &$processed): void {
                $lifecycle->applyPendingPlanChange($subscription);
                $lifecycle->renewPeriod($subscription);
                $processed++;
            });

        $this->info("Processed {$processed} subscription lifecycle transitions.");

        return self::SUCCESS;
    }
}
