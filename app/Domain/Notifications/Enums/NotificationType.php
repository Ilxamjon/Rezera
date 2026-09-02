<?php

namespace App\Domain\Notifications\Enums;

enum NotificationType: string
{
    case ReservationCreated = 'reservation_created';
    case ReservationConfirmed = 'reservation_confirmed';
    case ReservationCancelled = 'reservation_cancelled';
    case ReservationCompleted = 'reservation_completed';
    case ReservationNoShow = 'reservation_no_show';
    case ReservationReminder = 'reservation_reminder';
    case PaymentCreated = 'payment_created';
    case PaymentProcessing = 'payment_processing';
    case PaymentSucceeded = 'payment_succeeded';
    case PaymentFailed = 'payment_failed';
    case PaymentRefunded = 'payment_refunded';
    case BusinessBookingReceived = 'business_booking_received';
    case BusinessBookingCancelled = 'business_booking_cancelled';
    case ReviewCreated = 'review_created';
    case ReviewResponseReceived = 'review_response_received';
    case ReviewReported = 'review_reported';
    case ReservationCheckedIn = 'reservation_checked_in';
    case ReservationCheckedOut = 'reservation_checked_out';
    case BusinessCustomerCheckedIn = 'business_customer_checked_in';
    case BusinessCustomerCheckedOut = 'business_customer_checked_out';
    case LoyaltyPointsEarned = 'loyalty_points_earned';
    case RewardRedeemed = 'reward_redeemed';
    case PointsExpiring = 'points_expiring';
    case ReferralRegistered = 'referral_registered';
    case ReferralQualified = 'referral_qualified';
    case ReferralRewarded = 'referral_rewarded';
    case BusinessCustomerRedeemedReward = 'customer_redeemed_reward';
    case BusinessCustomerEarnedLoyaltyPoints = 'customer_earned_loyalty_points';
    case AvailabilityAlert = 'availability_alert';
    case BusinessVerificationSubmitted = 'business_verification_submitted';
    case BusinessVerificationApproved = 'business_verification_approved';
    case BusinessVerificationRejected = 'business_verification_rejected';
    case BusinessOnboardingCompleted = 'business_onboarding_completed';
    case BusinessReadyForReservations = 'business_ready_for_reservations';
    case BusinessStaffInvited = 'business_staff_invited';
    case BusinessStaffAdded = 'business_staff_added';
    case SystemNotification = 'system_notification';

    /**
     * @return list<NotificationChannel>
     */
    public function defaultChannels(): array
    {
        return match ($this) {
            self::ReservationCreated,
            self::ReservationConfirmed,
            self::ReservationCancelled,
            self::ReservationCompleted,
            self::ReservationNoShow,
            self::PaymentCreated,
            self::PaymentProcessing,
            self::PaymentSucceeded,
            self::PaymentFailed,
            self::PaymentRefunded,
            self::BusinessBookingReceived,
            self::BusinessBookingCancelled,
            self::ReviewCreated,
            self::ReviewResponseReceived,
            self::ReviewReported,
            self::ReservationCheckedIn,
            self::ReservationCheckedOut,
            self::BusinessCustomerCheckedIn,
            self::BusinessCustomerCheckedOut,
            self::LoyaltyPointsEarned,
            self::RewardRedeemed,
            self::PointsExpiring,
            self::ReferralRegistered,
            self::ReferralQualified,
            self::ReferralRewarded,
            self::BusinessCustomerRedeemedReward,
            self::BusinessCustomerEarnedLoyaltyPoints,
            self::AvailabilityAlert,
            self::BusinessVerificationSubmitted,
            self::BusinessVerificationApproved,
            self::BusinessVerificationRejected,
            self::BusinessOnboardingCompleted,
            self::BusinessReadyForReservations,
            self::BusinessStaffInvited,
            self::BusinessStaffAdded,
            self::SystemNotification => [NotificationChannel::Database],
            self::ReservationReminder => [NotificationChannel::Database, NotificationChannel::Push],
        };
    }

    public function isUserConfigurable(): bool
    {
        return $this !== self::SystemNotification;
    }
}
