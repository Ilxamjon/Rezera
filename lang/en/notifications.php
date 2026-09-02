<?php

return [
    'types' => [
        'reservation_created' => [
            'title' => 'Reservation created',
            'body' => 'Your reservation :reservation_number at :business_name has been created.',
        ],
        'reservation_confirmed' => [
            'title' => 'Reservation confirmed',
            'body' => 'Your reservation :reservation_number at :business_name is confirmed for :date at :start_time.',
        ],
        'reservation_cancelled' => [
            'title' => 'Reservation cancelled',
            'body' => 'Your reservation :reservation_number at :business_name has been cancelled.',
        ],
        'reservation_completed' => [
            'title' => 'Reservation completed',
            'body' => 'Your reservation :reservation_number at :business_name is completed.',
        ],
        'reservation_no_show' => [
            'title' => 'Reservation marked as no-show',
            'body' => 'Your reservation :reservation_number at :business_name was marked as no-show.',
        ],
        'reservation_reminder' => [
            'title' => 'Reservation reminder',
            'body' => 'Reminder: :reservation_number at :business_name starts at :start_time on :date.',
        ],
        'payment_created' => [
            'title' => 'Payment created',
            'body' => 'Payment :payment_number for reservation :reservation_number has been created.',
        ],
        'payment_processing' => [
            'title' => 'Payment processing',
            'body' => 'Payment :payment_number is being processed.',
        ],
        'payment_succeeded' => [
            'title' => 'Payment successful',
            'body' => 'Payment :payment_number for :amount :currency was successful.',
        ],
        'payment_failed' => [
            'title' => 'Payment failed',
            'body' => 'Payment :payment_number could not be completed.',
        ],
        'payment_refunded' => [
            'title' => 'Payment refunded',
            'body' => 'Payment :payment_number has been refunded.',
        ],
        'business_booking_received' => [
            'title' => 'New booking',
            'body' => 'New reservation :reservation_number for :resource_name on :date at :start_time.',
        ],
        'business_booking_cancelled' => [
            'title' => 'Booking cancelled',
            'body' => 'Reservation :reservation_number was cancelled by the customer.',
        ],
        'review_created' => [
            'title' => 'New review',
            'body' => ':business_name received a new :rating-star review.',
        ],
        'review_response_received' => [
            'title' => 'Business replied to your review',
            'body' => ':business_name responded to your review.',
        ],
        'review_reported' => [
            'title' => 'Review reported',
            'body' => 'A review for :business_name was reported and needs attention.',
        ],
        'availability_alert' => [
            'title' => 'Matching availability found',
            'body' => ':resource_name at :business_name is available on :date from :start_time to :end_time.',
        ],
        'reservation_checked_in' => [
            'title' => 'Check-in successful',
            'body' => 'You have checked in for reservation :reservation_number at :business_name.',
        ],
        'reservation_checked_out' => [
            'title' => 'Check-out successful',
            'body' => 'You have checked out from reservation :reservation_number at :business_name.',
        ],
        'business_customer_checked_in' => [
            'title' => 'Customer checked in',
            'body' => 'A customer checked in for reservation :reservation_number.',
        ],
        'business_customer_checked_out' => [
            'title' => 'Customer checked out',
            'body' => 'A customer checked out from reservation :reservation_number.',
        ],
        'loyalty_points_earned' => [
            'title' => 'Loyalty points earned',
            'body' => 'You earned :points points at :business_name.',
        ],
        'reward_redeemed' => [
            'title' => 'Reward redeemed',
            'body' => 'You redeemed :reward_name. Your code is :code.',
        ],
        'points_expiring' => [
            'title' => 'Points expiring soon',
            'body' => 'Some of your loyalty points will expire soon.',
        ],
        'referral_registered' => [
            'title' => 'New referral',
            'body' => 'Someone registered using your referral code.',
        ],
        'referral_qualified' => [
            'title' => 'Referral qualified',
            'body' => 'Your referral completed their first reservation.',
        ],
        'referral_rewarded' => [
            'title' => 'Referral reward',
            'body' => 'You received a referral reward.',
        ],
        'customer_redeemed_reward' => [
            'title' => 'Customer redeemed reward',
            'body' => 'A customer redeemed a loyalty reward.',
        ],
        'customer_earned_loyalty_points' => [
            'title' => 'Customer earned points',
            'body' => 'A customer earned loyalty points.',
        ],
        'business_verification_submitted' => [
            'title' => 'Business verification submitted',
            'body' => ':business_name submitted verification for review.',
        ],
        'business_verification_approved' => [
            'title' => 'Business verification approved',
            'body' => ':business_name verification was approved.',
        ],
        'business_verification_rejected' => [
            'title' => 'Business verification rejected',
            'body' => ':business_name verification was rejected.',
        ],
        'business_onboarding_completed' => [
            'title' => 'Onboarding completed',
            'body' => ':business_name onboarding is complete.',
        ],
        'business_ready_for_reservations' => [
            'title' => 'Ready for reservations',
            'body' => ':business_name is ready to accept reservations.',
        ],
        'business_staff_invited' => [
            'title' => 'Business team invitation',
            'body' => 'You have been invited to join :business_name as :member_role.',
        ],
        'business_staff_added' => [
            'title' => 'Added to business team',
            'body' => 'You have been added to :business_name as :member_role.',
        ],
        'system_notification' => [
            'title' => 'System notification',
            'body' => 'You have a new system notification.',
        ],
    ],
];
