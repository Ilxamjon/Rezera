<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;
use App\Services\Authorization\PlatformAuthorizationService;
use App\Services\Reviews\ReviewEligibilityService;

class ReviewPolicy extends BasePolicy
{
    public function __construct(
        private readonly ReviewEligibilityService $eligibility,
        private readonly BusinessAuthorizationService $businessAuthorization,
        private readonly PlatformAuthorizationService $platformAuthorization,
    ) {}

    public function create(User $user, Business $business): bool
    {
        return $user->isActive();
    }

    public function view(?User $user, Review $review): bool
    {
        if ($this->eligibility->isPubliclyVisible($review)) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($review->user_id === $user->id) {
            return true;
        }

        if ($this->businessAuthorization->canOperateBookings($user, $review->business_id)) {
            return true;
        }

        return $this->platformAuthorization->isStaff($user);
    }

    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id && $user->isActive();
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id && $user->isActive();
    }

    public function respond(User $user, Review $review): bool
    {
        return $this->businessAuthorization->canAdministerBookings($user, $review->business_id);
    }

    public function moderate(User $user): bool
    {
        return $this->platformAuthorization->can($user, \App\Domain\Platform\Enums\PlatformPermission::ReviewsManage);
    }

    public function report(User $user, Review $review): bool
    {
        return $user->isActive() && ! $review->trashed();
    }
}
