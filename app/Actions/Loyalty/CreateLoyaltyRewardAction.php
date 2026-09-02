<?php

namespace App\Actions\Loyalty;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Loyalty\Enums\LoyaltyRewardType;
use App\Models\Business;
use App\Models\LoyaltyReward;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateLoyaltyRewardAction
{
    public function __construct(
        private readonly CreateAuditLogAction $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Business $business, User $actor, array $data): LoyaltyReward
    {
        return DB::transaction(function () use ($business, $actor, $data): LoyaltyReward {
            $reward = LoyaltyReward::query()->create([
                'business_id' => $business->id,
                ...$data,
                'reward_type' => LoyaltyRewardType::from($data['reward_type']),
            ]);

            $this->auditLog->execute(
                action: 'loyalty.reward_created',
                entityType: 'loyalty_reward',
                entityId: $reward->id,
                actor: $actor,
                newValues: $reward->only(['name', 'points_cost', 'reward_type', 'is_active']),
                metadata: ['business_id' => $business->id],
            );

            return $reward;
        });
    }
}
