<?php

namespace App\Actions\Loyalty;

use App\Actions\Platform\CreateAuditLogAction;
use App\Models\Business;
use App\Models\LoyaltyProgram;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateLoyaltyProgramAction
{
    public function __construct(
        private readonly CreateAuditLogAction $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Business $business, User $actor, array $data): LoyaltyProgram
    {
        return DB::transaction(function () use ($business, $actor, $data): LoyaltyProgram {
            $program = LoyaltyProgram::query()->firstOrNew(['business_id' => $business->id]);
            $oldValues = $program->exists ? $program->only([
                'name', 'description', 'is_enabled', 'earn_rate_points', 'earn_amount',
                'flat_points_per_reservation', 'minimum_qualifying_amount',
                'max_points_per_transaction', 'points_expiration_days',
            ]) : null;

            $program->fill([
                'name' => $data['name'] ?? $program->name ?? 'Rezera Rewards',
                'description' => $data['description'] ?? $program->description,
                'is_enabled' => $data['is_enabled'] ?? $program->is_enabled ?? false,
                'earn_rate_points' => $data['earn_rate_points'] ?? $program->earn_rate_points ?? 1,
                'earn_amount' => $data['earn_amount'] ?? $program->earn_amount ?? 1000,
                'flat_points_per_reservation' => $data['flat_points_per_reservation'] ?? $program->flat_points_per_reservation,
                'minimum_qualifying_amount' => $data['minimum_qualifying_amount'] ?? $program->minimum_qualifying_amount,
                'max_points_per_transaction' => $data['max_points_per_transaction'] ?? $program->max_points_per_transaction,
                'points_expiration_days' => $data['points_expiration_days'] ?? $program->points_expiration_days,
                'metadata' => $data['metadata'] ?? $program->metadata,
            ]);
            $program->save();

            $this->auditLog->execute(
                action: $oldValues === null ? 'loyalty.program_enabled' : 'loyalty.program_updated',
                entityType: 'loyalty_program',
                entityId: $program->id,
                actor: $actor,
                oldValues: $oldValues,
                newValues: $program->only([
                    'name', 'description', 'is_enabled', 'earn_rate_points', 'earn_amount',
                    'flat_points_per_reservation', 'minimum_qualifying_amount',
                    'max_points_per_transaction', 'points_expiration_days',
                ]),
                metadata: ['business_id' => $business->id],
            );

            return $program;
        });
    }
}
