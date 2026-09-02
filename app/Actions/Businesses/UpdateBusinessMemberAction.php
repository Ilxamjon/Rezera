<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Models\Business;
use App\Models\BusinessMember;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UpdateBusinessMemberAction
{
    public function __construct(
        private readonly RecordBusinessStaffAuditAction $staffAudit,
    ) {}

    /**
     * @param  array{member_role?: string, job_title?: ?string}  $attributes
     */
    public function execute(
        Business $business,
        BusinessMember $member,
        array $attributes,
        ?Request $request = null,
    ): BusinessMember {
        if ($member->member_role === BusinessMemberRole::Owner && isset($attributes['member_role'])) {
            throw ValidationException::withMessages([
                'member_role' => [__('business.cannot_change_owner_role')],
            ]);
        }

        if (isset($attributes['member_role']) && BusinessMemberRole::from($attributes['member_role']) === BusinessMemberRole::Owner) {
            throw ValidationException::withMessages([
                'member_role' => [__('business.ownership_transfer_not_supported')],
            ]);
        }

        $oldValues = [
            'member_role' => $member->member_role->value,
            'job_title' => $member->job_title,
        ];

        $updates = [];

        if (isset($attributes['member_role'])) {
            $updates['member_role'] = BusinessMemberRole::from($attributes['member_role']);
        }

        if (array_key_exists('job_title', $attributes)) {
            $updates['job_title'] = $attributes['job_title'];
        }

        $member->update($updates);
        $member = $member->fresh();

        $this->staffAudit->execute(
            action: 'business_staff.updated',
            business: $business,
            actor: $request?->user(),
            member: $member,
            oldValues: $oldValues,
            newValues: [
                'member_role' => $member->member_role->value,
                'job_title' => $member->job_title,
            ],
            request: $request,
        );

        return $member;
    }
}
