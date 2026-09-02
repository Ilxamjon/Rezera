<?php

namespace App\Actions\Loyalty;

use App\Actions\Platform\CreateAuditLogAction;
use App\Models\Business;
use App\Models\LoyaltyReward;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteLoyaltyRewardAction
{
    public function __construct(
        private readonly CreateAuditLogAction $auditLog,
    ) {}

    public function execute(LoyaltyReward $reward, User $actor): void
    {
        DB::transaction(function () use ($reward, $actor): void {
            $this->auditLog->execute(
                action: 'loyalty.reward_updated',
                entityType: 'loyalty_reward',
                entityId: $reward->id,
                actor: $actor,
                oldValues: $reward->only(['name', 'is_active']),
                metadata: ['business_id' => $reward->business_id, 'deleted' => true],
            );

            $reward->update(['is_active' => false]);
        });
    }
}
