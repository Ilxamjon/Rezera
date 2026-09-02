<?php

namespace App\Actions\Loyalty;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Loyalty\Enums\LoyaltyRewardType;
use App\Models\LoyaltyReward;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateLoyaltyRewardAction
{
    public function __construct(
        private readonly CreateAuditLogAction $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(LoyaltyReward $reward, User $actor, array $data): LoyaltyReward
    {
        return DB::transaction(function () use ($reward, $actor, $data): LoyaltyReward {
            $oldValues = $reward->only(['name', 'points_cost', 'reward_type', 'is_active', 'stock']);

            if (isset($data['reward_type'])) {
                $data['reward_type'] = LoyaltyRewardType::from($data['reward_type']);
            }

            $reward->update($data);

            $this->auditLog->execute(
                action: 'loyalty.reward_updated',
                entityType: 'loyalty_reward',
                entityId: $reward->id,
                actor: $actor,
                oldValues: $oldValues,
                newValues: $reward->only(['name', 'points_cost', 'reward_type', 'is_active', 'stock']),
                metadata: ['business_id' => $reward->business_id],
            );

            return $reward->fresh();
        });
    }
}
