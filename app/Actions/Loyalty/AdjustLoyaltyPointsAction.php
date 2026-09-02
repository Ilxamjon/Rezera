<?php

namespace App\Actions\Loyalty;

use App\Actions\Platform\CreateAuditLogAction;
use App\Models\Business;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Support\Facades\DB;

final class AdjustLoyaltyPointsAction
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
        private readonly CreateAuditLogAction $auditLog,
    ) {}

    public function execute(Business $business, User $customer, User $actor, int $points, string $reason): void
    {
        DB::transaction(function () use ($business, $customer, $actor, $points, $reason): void {
            $transaction = $this->loyaltyService->adjustPoints($business, $customer, $points, $reason, $actor);

            $this->auditLog->execute(
                action: 'loyalty.points_adjusted',
                entityType: 'loyalty_transaction',
                entityId: $transaction->id,
                actor: $actor,
                newValues: [
                    'points' => $points,
                    'reason' => $reason,
                    'customer_user_id' => $customer->id,
                ],
                metadata: ['business_id' => $business->id],
            );
        });
    }
}
