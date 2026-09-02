<?php

namespace App\Providers;

use App\Events\Loyalty\LoyaltyPointsEarned;
use App\Events\Loyalty\LoyaltyRewardRedeemed;
use App\Events\Payments\PaymentCreated;
use App\Events\Payments\PaymentFailed;
use App\Events\Payments\PaymentRefunded;
use App\Events\Payments\PaymentSucceeded;
use App\Events\Businesses\BusinessBecameReservationReady;
use App\Events\Businesses\BusinessOnboardingCompleted;
use App\Events\Businesses\BusinessVerificationApproved;
use App\Events\Businesses\BusinessVerificationRejected;
use App\Events\Businesses\BusinessVerificationSubmitted;
use App\Listeners\Notifications\DispatchBusinessVerificationNotifications;
use App\Listeners\Subscriptions\ActivateSubscriptionOnPaymentSucceeded;
use App\Events\Referrals\ReferralQualified;
use App\Events\Referrals\ReferralRegistered;
use App\Events\Referrals\ReferralRewarded;
use App\Events\Reservations\ReservationCheckedIn;
use App\Events\Reservations\ReservationCheckedOut;
use App\Events\Reservations\ReservationCreated;
use App\Events\Reservations\ReservationStatusChanged;
use App\Listeners\SavedSearches\ReevaluateSavedSearchesOnReservationChange;
use App\Events\Reviews\ReviewCreated;
use App\Events\Reviews\ReviewReported;
use App\Events\Reviews\ReviewResponsePublished;
use App\Listeners\Loyalty\ProcessLoyaltyOnReservationCompleted;
use App\Listeners\Loyalty\ReverseLoyaltyOnPaymentRefunded;
use App\Listeners\Notifications\DispatchCheckInNotifications;
use App\Listeners\Notifications\DispatchLoyaltyNotifications;
use App\Listeners\Notifications\DispatchPaymentNotifications;
use App\Listeners\Notifications\DispatchReservationNotifications;
use App\Listeners\Notifications\DispatchReviewNotifications;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        ReservationCreated::class => [
            [DispatchReservationNotifications::class, 'handleReservationCreated'],
        ],
        ReservationStatusChanged::class => [
            [DispatchReservationNotifications::class, 'handleReservationStatusChanged'],
            ProcessLoyaltyOnReservationCompleted::class,
            ReevaluateSavedSearchesOnReservationChange::class,
        ],
        ReservationCheckedIn::class => [
            [DispatchCheckInNotifications::class, 'handleCheckedIn'],
        ],
        ReservationCheckedOut::class => [
            [DispatchCheckInNotifications::class, 'handleCheckedOut'],
        ],
        PaymentCreated::class => [
            [DispatchPaymentNotifications::class, 'handlePaymentCreated'],
        ],
        PaymentSucceeded::class => [
            [DispatchPaymentNotifications::class, 'handlePaymentSucceeded'],
            ActivateSubscriptionOnPaymentSucceeded::class,
        ],
        PaymentFailed::class => [
            [DispatchPaymentNotifications::class, 'handlePaymentFailed'],
        ],
        PaymentRefunded::class => [
            [DispatchPaymentNotifications::class, 'handlePaymentRefunded'],
            ReverseLoyaltyOnPaymentRefunded::class,
        ],
        ReviewCreated::class => [
            [DispatchReviewNotifications::class, 'handleReviewCreated'],
        ],
        ReviewResponsePublished::class => [
            [DispatchReviewNotifications::class, 'handleReviewResponsePublished'],
        ],
        ReviewReported::class => [
            [DispatchReviewNotifications::class, 'handleReviewReported'],
        ],
        LoyaltyPointsEarned::class => [
            [DispatchLoyaltyNotifications::class, 'handlePointsEarned'],
        ],
        LoyaltyRewardRedeemed::class => [
            [DispatchLoyaltyNotifications::class, 'handleRewardRedeemed'],
        ],
        ReferralRegistered::class => [
            [DispatchLoyaltyNotifications::class, 'handleReferralRegistered'],
        ],
        ReferralQualified::class => [
            [DispatchLoyaltyNotifications::class, 'handleReferralQualified'],
        ],
        ReferralRewarded::class => [
            [DispatchLoyaltyNotifications::class, 'handleReferralRewarded'],
        ],
        BusinessVerificationSubmitted::class => [
            [DispatchBusinessVerificationNotifications::class, 'handleSubmitted'],
        ],
        BusinessVerificationApproved::class => [
            [DispatchBusinessVerificationNotifications::class, 'handleApproved'],
        ],
        BusinessVerificationRejected::class => [
            [DispatchBusinessVerificationNotifications::class, 'handleRejected'],
        ],
        BusinessOnboardingCompleted::class => [
            [DispatchBusinessVerificationNotifications::class, 'handleOnboardingCompleted'],
        ],
        BusinessBecameReservationReady::class => [
            [DispatchBusinessVerificationNotifications::class, 'handleReservationReady'],
        ],
    ];
}
