<?php

namespace App\Policies;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;
use App\Services\Reservations\ReservationTransitionService;

class ReservationPolicy extends BasePolicy
{
    public function __construct(
        private readonly BusinessAuthorizationService $businessAuthorization,
        private readonly ReservationTransitionService $transitionService,
    ) {}

    public function view(User $user, Reservation $reservation): bool
    {
        return $reservation->customer_id === $user->id
            || $this->businessAuthorization->canManageBookings($user, $reservation->business_id)
            || $user->isPlatformAdmin();
    }

    public function create(User $user, Business $business): bool
    {
        return $user->isActive() && $business->isPubliclyVisible();
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        if ($reservation->customer_id === $user->id) {
            return $user->isActive();
        }

        return $this->businessAuthorization->canAdministerBookings($user, $reservation->business_id)
            && $this->transitionService->canTransition($reservation->status, ReservationStatus::Cancelled);
    }

    public function viewBusiness(User $user, Business $business): bool
    {
        return $this->businessAuthorization->canManageBookings($user, $business->id);
    }

    public function updateStatus(User $user, Reservation $reservation): bool
    {
        return $this->businessAuthorization->canOperateBookings($user, $reservation->business_id)
            || $user->isPlatformAdmin();
    }

    public function changeStatus(User $user, Reservation $reservation, ReservationStatus $toStatus): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        $businessId = $reservation->business_id;

        $adminStatuses = [
            ReservationStatus::Confirmed,
            ReservationStatus::Rejected,
        ];

        $operationalStatuses = [
            ReservationStatus::Completed,
            ReservationStatus::CheckedIn,
            ReservationStatus::NoShow,
        ];

        if (in_array($toStatus, $adminStatuses, true)) {
            return $this->businessAuthorization->canAdministerBookings($user, $businessId);
        }

        if (in_array($toStatus, $operationalStatuses, true)) {
            return $this->businessAuthorization->canOperateBookings($user, $businessId);
        }

        return false;
    }

    public function checkIn(User $user, Reservation $reservation): bool
    {
        if ($reservation->customer_id === $user->id) {
            return $user->isActive();
        }

        return $this->businessAuthorization->canOperateBookings($user, $reservation->business_id);
    }

    public function checkOut(User $user, Reservation $reservation): bool
    {
        return $this->checkIn($user, $reservation);
    }
}
