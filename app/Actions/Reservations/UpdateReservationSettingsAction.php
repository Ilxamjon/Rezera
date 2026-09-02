<?php

namespace App\Actions\Reservations;

use App\Actions\Platform\CreateAuditLogAction;
use App\Models\BookingPolicy;
use App\Models\Business;
use App\Models\User;
use App\Services\Reservations\BusinessSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class UpdateReservationSettingsAction
{
    public function __construct(
        private readonly BusinessSettingsService $settingsService,
        private readonly CreateAuditLogAction $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(
        Business $business,
        array $attributes,
        ?User $actor = null,
        ?Request $request = null,
    ): BookingPolicy {
        return DB::transaction(function () use ($business, $attributes, $actor, $request): BookingPolicy {
            $policy = $this->settingsService->getForBusiness($business);
            $oldValues = $this->settingsService->toArray($policy);

            $updated = $this->settingsService->update($business, $attributes);
            $newValues = $this->settingsService->toArray($updated);

            $this->audit->execute(
                action: 'reservation_settings.updated',
                entityType: 'booking_policy',
                entityId: $updated->id,
                actor: $actor,
                oldValues: $oldValues,
                newValues: $newValues,
                metadata: ['business_id' => $business->id],
                request: $request,
            );

            return $updated;
        });
    }
}
