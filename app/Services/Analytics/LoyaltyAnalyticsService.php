<?php

namespace App\Services\Analytics;

use App\Domain\Loyalty\Enums\LoyaltyRedemptionStatus;
use App\Domain\Loyalty\Enums\LoyaltyTransactionType;
use App\Models\Business;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTransaction;
use App\Support\Analytics\AnalyticsDateRange;
use App\Support\Analytics\AnalyticsFilter;

final class LoyaltyAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(Business $business, AnalyticsDateRange $range, AnalyticsFilter $filter): array
    {
        $transactionQuery = LoyaltyTransaction::query()
            ->where('business_id', $business->id)
            ->whereBetween('created_at', [$range->utcStart(), $range->utcEnd()]);

        $points = (clone $transactionQuery)
            ->selectRaw("coalesce(sum(case when type = ? and points > 0 then points else 0 end), 0) as earned", [LoyaltyTransactionType::Earned->value])
            ->selectRaw("coalesce(sum(case when type = ? then abs(points) else 0 end), 0) as redeemed", [LoyaltyTransactionType::Redeemed->value])
            ->selectRaw("coalesce(sum(case when type = ? then abs(points) else 0 end), 0) as expired", [LoyaltyTransactionType::Expired->value])
            ->selectRaw("coalesce(sum(case when type = ? then abs(points) else 0 end), 0) as reversed", [LoyaltyTransactionType::Reversed->value])
            ->first();

        $members = LoyaltyAccount::query()
            ->where('business_id', $business->id)
            ->count();

        $redemptions = LoyaltyRedemption::query()
            ->where('business_id', $business->id)
            ->whereBetween('created_at', [$range->utcStart(), $range->utcEnd()])
            ->whereNotIn('status', [LoyaltyRedemptionStatus::Cancelled])
            ->count();

        $topRewards = LoyaltyRedemption::query()
            ->where('business_id', $business->id)
            ->whereBetween('created_at', [$range->utcStart(), $range->utcEnd()])
            ->whereNotIn('status', [LoyaltyRedemptionStatus::Cancelled])
            ->select('loyalty_reward_id')
            ->selectRaw('count(*) as total')
            ->groupBy('loyalty_reward_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $reward = LoyaltyReward::query()->find($row->loyalty_reward_id);

                return [
                    'reward_id' => $row->loyalty_reward_id,
                    'name' => $reward?->name,
                    'redemptions' => (int) $row->total,
                ];
            })
            ->values()
            ->all();

        return [
            'loyalty_members' => $members,
            'points_earned' => (int) ($points->earned ?? 0),
            'points_redeemed' => (int) ($points->redeemed ?? 0),
            'points_expired' => (int) ($points->expired ?? 0),
            'points_reversed' => (int) ($points->reversed ?? 0),
            'reward_redemptions' => $redemptions,
            'active_rewards' => LoyaltyReward::query()
                ->where('business_id', $business->id)
                ->active()
                ->count(),
            'most_redeemed_rewards' => $topRewards,
        ];
    }
}
