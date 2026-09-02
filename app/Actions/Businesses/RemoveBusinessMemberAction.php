<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Models\Business;
use App\Models\BusinessMember;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RemoveBusinessMemberAction
{
    public function __construct(
        private readonly RecordBusinessStaffAuditAction $staffAudit,
    ) {}

    public function execute(Business $business, BusinessMember $member, ?Request $request = null): void
    {
        if ($member->member_role === BusinessMemberRole::Owner) {
            $ownerCount = BusinessMember::query()
                ->where('business_id', $business->id)
                ->where('status', BusinessMemberStatus::Active)
                ->where('member_role', BusinessMemberRole::Owner)
                ->count();

            if ($ownerCount <= 1) {
                throw ValidationException::withMessages([
                    'member' => [__('business.cannot_remove_last_owner')],
                ]);
            }
        }

        $oldValues = [
            'member_role' => $member->member_role->value,
            'job_title' => $member->job_title,
            'user_id' => $member->user_id,
        ];

        $member->update(['status' => BusinessMemberStatus::Revoked]);

        $this->staffAudit->execute(
            action: 'business_staff.removed',
            business: $business,
            actor: $request?->user(),
            member: $member,
            oldValues: $oldValues,
            request: $request,
        );
    }
}
