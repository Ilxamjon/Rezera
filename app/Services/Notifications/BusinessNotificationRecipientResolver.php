<?php

namespace App\Services\Notifications;

use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;
use Illuminate\Support\Collection;

final class BusinessNotificationRecipientResolver
{
    public function __construct(
        private readonly BusinessAuthorizationService $authorization,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function bookingManagers(string $businessId): Collection
    {
        $members = BusinessMember::query()
            ->where('business_id', $businessId)
            ->where('status', BusinessMemberStatus::Active)
            ->with('user')
            ->get();

        return $members
            ->filter(fn (BusinessMember $member): bool => $member->user !== null
                && $this->authorization->canManageBookings($member->user, $businessId))
            ->map(fn (BusinessMember $member): User => $member->user)
            ->unique('id')
            ->values();
    }
}
