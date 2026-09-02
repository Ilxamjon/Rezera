<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\Enums\BusinessMemberInvitationStatus;
use App\Models\BusinessMemberInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeclineBusinessMemberInvitationAction
{
    public function __construct(
        private readonly RecordBusinessStaffAuditAction $staffAudit,
    ) {}

    public function execute(BusinessMemberInvitation $invitation, User $user, ?Request $request = null): BusinessMemberInvitation
    {
        if ($invitation->phone !== $user->phone) {
            throw ValidationException::withMessages([
                'invitation' => [__('business.invitation_not_for_user')],
            ]);
        }

        if ($invitation->status !== BusinessMemberInvitationStatus::Pending) {
            throw ValidationException::withMessages([
                'invitation' => [__('business.invitation_not_pending')],
            ]);
        }

        return DB::transaction(function () use ($invitation, $user, $request): BusinessMemberInvitation {
            $invitation->update([
                'status' => BusinessMemberInvitationStatus::Declined,
                'invited_user_id' => $user->id,
                'responded_at' => now(),
            ]);

            $this->staffAudit->execute(
                action: 'business_staff.invitation_declined',
                business: $invitation->business,
                actor: $user,
                newValues: ['invitation_id' => $invitation->id],
                request: $request,
            );

            return $invitation->fresh(['business']);
        });
    }
}
