<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\Enums\BusinessMemberInvitationStatus;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Models\BusinessMember;
use App\Models\BusinessMemberInvitation;
use App\Models\User;
use App\Services\Businesses\BusinessStaffInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptBusinessMemberInvitationAction
{
    public function __construct(
        private readonly AddBusinessMemberAction $addMember,
        private readonly BusinessStaffInvitationService $invitations,
        private readonly RecordBusinessStaffAuditAction $staffAudit,
    ) {}

    public function execute(BusinessMemberInvitation $invitation, User $user, ?Request $request = null): BusinessMember
    {
        $this->invitations->expireStaleInvitations();

        if ($invitation->phone !== $user->phone) {
            throw ValidationException::withMessages([
                'invitation' => [__('business.invitation_not_for_user')],
            ]);
        }

        if ($invitation->status !== BusinessMemberInvitationStatus::Pending || $invitation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'invitation' => [__('business.invitation_not_pending')],
            ]);
        }

        return DB::transaction(function () use ($invitation, $user, $request): BusinessMember {
            $member = $this->addMember->execute(
                business: $invitation->business,
                user: $user,
                role: $invitation->member_role,
                jobTitle: $invitation->job_title,
                invitedBy: User::query()->find($invitation->invited_by_user_id),
                notify: false,
            );

            $invitation->update([
                'status' => BusinessMemberInvitationStatus::Accepted,
                'invited_user_id' => $user->id,
                'responded_at' => now(),
            ]);

            $this->staffAudit->execute(
                action: 'business_staff.invitation_accepted',
                business: $invitation->business,
                actor: $user,
                member: $member,
                newValues: [
                    'invitation_id' => $invitation->id,
                    'member_role' => $member->member_role->value,
                ],
                request: $request,
            );

            $this->staffAudit->notifyStaffAdded(
                user: $user,
                business: $invitation->business,
                role: $member->member_role,
                notifications: app(\App\Services\Notifications\NotificationService::class),
            );

            return $member->fresh(['user', 'business']);
        });
    }
}
