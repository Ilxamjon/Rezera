<?php

namespace App\Actions\Platform;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ChangeUserStatusAction
{
    public function __construct(
        private readonly CreateAuditLogAction $audit,
    ) {}

    public function execute(User $user, UserStatus $status, User $actor, ?string $reason = null, ?Request $request = null): User
    {
        if ($actor->id === $user->id && $status !== UserStatus::Active) {
            throw ValidationException::withMessages([
                'status' => [__('admin.cannot_change_own_status')],
            ]);
        }

        $old = ['status' => $user->status?->value];
        $user->update(['status' => $status]);

        $this->audit->execute(
            action: 'user.status_changed',
            entityType: 'user',
            entityId: $user->id,
            actor: $actor,
            oldValues: $old,
            newValues: ['status' => $status->value, 'reason' => $reason],
            request: $request,
        );

        return $user->fresh();
    }
}
