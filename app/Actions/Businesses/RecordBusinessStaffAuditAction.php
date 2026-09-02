<?php

namespace App\Actions\Businesses;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Notifications\Enums\NotificationType;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;

final class RecordBusinessStaffAuditAction
{
    public function __construct(
        private readonly CreateAuditLogAction $audit,
    ) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function execute(
        string $action,
        Business $business,
        ?User $actor,
        ?BusinessMember $member = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
    ): void {
        $this->audit->execute(
            action: $action,
            entityType: 'business_member',
            entityId: $member?->id,
            actor: $actor,
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: ['business_id' => $business->id],
            request: $request,
        );
    }

    public function notifyStaffAdded(User $user, Business $business, BusinessMemberRole $role, NotificationService $notifications): void
    {
        $notifications->notifyUser(
            user: $user,
            type: NotificationType::BusinessStaffAdded,
            entityKey: 'member:'.$business->id.':'.$user->id,
            placeholders: [
                'business_name' => $business->name,
                'member_role' => $role->value,
            ],
            data: [
                'business_id' => $business->id,
                'member_role' => $role->value,
            ],
        );
    }
}
