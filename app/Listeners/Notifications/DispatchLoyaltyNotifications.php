<?php

namespace App\Listeners\Notifications;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Events\Loyalty\LoyaltyPointsEarned;
use App\Events\Loyalty\LoyaltyRewardRedeemed;
use App\Events\Referrals\ReferralQualified;
use App\Events\Referrals\ReferralRegistered;
use App\Events\Referrals\ReferralRewarded;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class DispatchLoyaltyNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function handlePointsEarned(LoyaltyPointsEarned $event): void
    {
        $user = $event->reservation->customer;

        if ($user === null) {
            return;
        }

        $this->notifications->notifyUser(
            user: $user,
            type: NotificationType::LoyaltyPointsEarned,
            entityKey: 'loyalty:'.$event->transaction->id.':earned',
            placeholders: [
                'points' => (string) $event->transaction->points,
                'business_name' => (string) ($event->reservation->business?->name ?? ''),
            ],
            data: [
                'transaction_id' => $event->transaction->id,
                'business_id' => $event->reservation->business_id,
                'points' => $event->transaction->points,
            ],
        );
    }

    public function handleRewardRedeemed(LoyaltyRewardRedeemed $event): void
    {
        $redemption = $event->redemption->loadMissing('reward');
        $user = $redemption->user;

        $this->notifications->notifyUser(
            user: $user,
            type: NotificationType::RewardRedeemed,
            entityKey: 'loyalty:redemption:'.$redemption->id,
            placeholders: [
                'reward_name' => (string) ($redemption->reward?->name ?? ''),
                'code' => $redemption->redemption_code,
            ],
            data: [
                'redemption_id' => $redemption->id,
                'business_id' => $redemption->business_id,
            ],
        );
    }

    public function handleReferralRegistered(ReferralRegistered $event): void
    {
        $this->notifications->notifyUser(
            user: $event->referral->referrer,
            type: NotificationType::ReferralRegistered,
            entityKey: 'referral:'.$event->referral->id.':registered',
            placeholders: [],
            data: ['referral_id' => $event->referral->id],
        );
    }

    public function handleReferralQualified(ReferralQualified $event): void
    {
        $this->notifications->notifyUser(
            user: $event->referral->referrer,
            type: NotificationType::ReferralQualified,
            entityKey: 'referral:'.$event->referral->id.':qualified',
            placeholders: [],
            data: ['referral_id' => $event->referral->id],
        );
    }

    public function handleReferralRewarded(ReferralRewarded $event): void
    {
        foreach ([$event->referral->referrer, $event->referral->referred] as $user) {
            $this->notifications->notifyUser(
                user: $user,
                type: NotificationType::ReferralRewarded,
                entityKey: 'referral:'.$event->referral->id.':rewarded:'.$user->id,
                placeholders: [],
                data: ['referral_id' => $event->referral->id],
            );
        }
    }
}
