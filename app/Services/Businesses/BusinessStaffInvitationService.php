<?php

namespace App\Services\Businesses;

use App\Domain\Businesses\Enums\BusinessMemberInvitationStatus;
use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Models\Business;
use App\Models\BusinessMemberInvitation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class BusinessStaffInvitationService
{
    public function expireStaleInvitations(): int
    {
        return BusinessMemberInvitation::query()
            ->where('status', BusinessMemberInvitationStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => BusinessMemberInvitationStatus::Expired,
                'responded_at' => now(),
            ]);
    }

    public function findPendingForPhone(string $phone, ?string $businessId = null): ?BusinessMemberInvitation
    {
        $this->expireStaleInvitations();

        $query = BusinessMemberInvitation::query()
            ->where('phone', $phone)
            ->where('status', BusinessMemberInvitationStatus::Pending)
            ->where('expires_at', '>', now());

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        return $query->latest('created_at')->first();
    }

    /**
     * @return list<BusinessMemberInvitation>
     */
    public function pendingForUser(User $user): array
    {
        $this->expireStaleInvitations();

        return BusinessMemberInvitation::query()
            ->where(function ($query) use ($user): void {
                $query->where('invited_user_id', $user->id)
                    ->orWhere('phone', $user->phone);
            })
            ->where('status', BusinessMemberInvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->with(['business:id,name', 'invitedBy:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public function expiryDate(): CarbonImmutable
    {
        return CarbonImmutable::now()->addDays((int) config('business_staff.invitation_expiry_days', 14));
    }

    public function assertRoleInvitable(BusinessMemberRole $role): void
    {
        if ($role === BusinessMemberRole::Owner) {
            throw ValidationException::withMessages([
                'member_role' => [__('business.cannot_invite_owner')],
            ]);
        }
    }

    public function assertNoDuplicatePending(Business $business, string $phone): void
    {
        $this->expireStaleInvitations();

        $exists = BusinessMemberInvitation::query()
            ->where('business_id', $business->id)
            ->where('phone', $phone)
            ->where('status', BusinessMemberInvitationStatus::Pending)
            ->where('expires_at', '>', now())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'phone' => [__('business.invitation_already_pending')],
            ]);
        }
    }
}
