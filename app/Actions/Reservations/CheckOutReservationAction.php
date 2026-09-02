<?php

namespace App\Actions\Reservations;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Reservations\Enums\CheckInMethod;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationSessionStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Events\Reservations\ReservationCheckedOut;
use App\Models\Reservation;
use App\Models\ReservationSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CheckOutReservationAction
{
    public function __construct(
        private readonly ChangeReservationStatusAction $changeStatus,
        private readonly CreateAuditLogAction $auditLog,
    ) {}

    public function execute(
        Reservation $reservation,
        User $actor,
        CheckInMethod $method,
        ReservationEventSource $source,
        ?Request $request = null,
    ): Reservation {
        if ($reservation->status === ReservationStatus::Completed && $reservation->checked_out_at !== null) {
            return $reservation->fresh(['business', 'resource.group', 'customer', 'activeSession', 'latestSession']);
        }

        return DB::transaction(function () use ($reservation, $actor, $method, $source, $request): Reservation {
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ReservationStatus::Completed && $locked->checked_out_at !== null) {
                return $locked->fresh(['business', 'resource.group', 'customer', 'activeSession', 'latestSession']);
            }

            $session = ReservationSession::query()
                ->where('reservation_id', $locked->id)
                ->where('status', ReservationSessionStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw ValidationException::withMessages([
                    'reservation' => [__('reservations.check_out.not_checked_in')],
                ]);
            }

            $now = now();
            $actualMinutes = (int) $session->started_at->diffInMinutes($now);
            $scheduledEnd = CarbonImmutable::instance($locked->end_at);
            $overtimeMinutes = $now->greaterThan($scheduledEnd)
                ? (int) $scheduledEnd->diffInMinutes($now)
                : 0;

            $session->update([
                'ended_at' => $now,
                'end_method' => $method,
                'ended_by_user_id' => $actor->id,
                'status' => ReservationSessionStatus::Completed,
                'metadata' => array_merge($session->metadata ?? [], [
                    'actual_duration_minutes' => $actualMinutes,
                    'overtime_minutes' => $overtimeMinutes,
                    'checked_out_at' => $now->toIso8601String(),
                ]),
            ]);

            $updated = $this->changeStatus->execute(
                reservation: $locked,
                toStatus: ReservationStatus::Completed,
                actor: $actor,
                source: $source,
            );

            $updated->update([
                'checked_out_at' => $now,
                'check_out_method' => $method->value,
                'checked_out_by_user_id' => $actor->id,
            ]);

            $this->auditLog->execute(
                action: 'reservation.checked_out',
                entityType: 'reservation',
                entityId: $updated->id,
                actor: $actor,
                newValues: [
                    'status' => ReservationStatus::Completed->value,
                    'check_out_method' => $method->value,
                    'actual_duration_minutes' => $actualMinutes,
                    'overtime_minutes' => $overtimeMinutes,
                ],
                metadata: ['business_id' => $updated->business_id, 'resource_id' => $updated->resource_id],
                request: $request,
            );

            $fresh = $updated->fresh(['business', 'resource.group', 'customer', 'activeSession', 'latestSession']);

            DB::afterCommit(fn () => ReservationCheckedOut::dispatch($fresh, $actor, $method));

            return $fresh;
        });
    }
}
