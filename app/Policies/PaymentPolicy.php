<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;

class PaymentPolicy extends BasePolicy
{
    public function __construct(
        private readonly BusinessAuthorizationService $businessAuthorization,
    ) {}

    public function view(User $user, Payment $payment): bool
    {
        return $payment->user_id === $user->id
            || $this->businessAuthorization->canManageBookings($user, $payment->business_id)
            || $user->isPlatformAdmin();
    }

    public function create(User $user, Reservation $reservation): bool
    {
        return $reservation->customer_id === $user->id;
    }

    public function viewBusiness(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBookings($user, $business->id);
    }
}
