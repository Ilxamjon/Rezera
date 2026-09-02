<?php

namespace App\Actions\Platform;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;

final class ChangeBusinessStatusAction
{
    public function __construct(
        private readonly CreateAuditLogAction $audit,
    ) {}

    public function execute(Business $business, BusinessStatus $status, User $actor, ?string $reason = null, ?Request $request = null): Business
    {
        $old = ['status' => $business->status?->value];
        $business->update([
            'status' => $status,
            'status_changed_at' => now(),
            'status_change_reason' => $reason,
        ]);

        $this->audit->execute(
            action: 'business.status_changed',
            entityType: 'business',
            entityId: $business->id,
            actor: $actor,
            oldValues: $old,
            newValues: ['status' => $status->value, 'reason' => $reason],
            request: $request,
        );

        return $business->fresh();
    }

    public function verify(
        Business $business,
        BusinessVerificationStatus $status,
        User $actor,
        ?string $note = null,
        ?Request $request = null,
    ): Business {
        $old = ['verification_status' => $business->verification_status?->value];
        $business->update([
            'verification_status' => $status,
            'verification_note' => $note,
            'reviewed_at' => now(),
            'reviewed_by_user_id' => $actor->id,
        ]);

        $this->audit->execute(
            action: 'business.verification_changed',
            entityType: 'business',
            entityId: $business->id,
            actor: $actor,
            oldValues: $old,
            newValues: ['verification_status' => $status->value, 'note' => $note],
            request: $request,
        );

        return $business->fresh();
    }
}
