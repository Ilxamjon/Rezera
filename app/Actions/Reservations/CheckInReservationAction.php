<?php

namespace App\Actions\Reservations;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Reservations\Enums\CheckInMethod;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationSessionStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Events\Reservations\ReservationCheckedIn;
use App\Models\Reservation;
use App\Models\ReservationSession;
use App\Models\User;
use App\Services\Reservations\CheckInEligibilityService;
use App\Services\Reservations\QrTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CheckInReservationAction
{
    public function __construct(
        private readonly CheckInEligibilityService $eligibility,
        private readonly ChangeReservationStatusAction $changeStatus,
        private readonly QrTokenService $qrTokenService,
        private readonly CreateAuditLogAction $auditLog,
    ) {}

    public function execute(
        Reservation $reservation,
        User $actor,
        CheckInMethod $method,
        ReservationEventSource $source,
        ?string $qrToken = null,
        ?Request $request = null,
    ): Reservation {
        if ($reservation->status === ReservationStatus::CheckedIn) {
            $existing = $reservation->fresh(['business', 'resource.group', 'customer', 'activeSession']);

            if ($existing?->activeSession !== null) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($reservation, $actor, $method, $source, $qrToken, $request): Reservation {
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ReservationStatus::CheckedIn) {
                $existing = $locked->fresh(['business', 'resource.group', 'customer', 'activeSession']);

                if ($existing?->activeSession !== null) {
                    return $existing;
                }
            }

            $this->eligibility->assertEligible($locked);

            if ($method === CheckInMethod::Qr) {
                if ($qrToken === null || trim($qrToken) === '') {
                    throw ValidationException::withMessages([
                        'qr_token' => [__('reservations.check_in.invalid_qr_token')],
                    ]);
                }

                $this->qrTokenService->validateForReservation(
                    $qrToken,
                    $locked->resource_id,
                    $locked->business_id,
                );
            }

            $hasActiveSession = ReservationSession::query()
                ->where('reservation_id', $locked->id)
                ->where('status', ReservationSessionStatus::Active->value)
                ->exists();

            if ($hasActiveSession) {
                throw ValidationException::withMessages([
                    'reservation' => [__('reservations.check_in.already_checked_in')],
                ]);
            }

            $now = now();

            ReservationSession::query()->create([
                'reservation_id' => $locked->id,
                'business_id' => $locked->business_id,
                'resource_id' => $locked->resource_id,
                'user_id' => $locked->customer_id,
                'started_at' => $now,
                'start_method' => $method,
                'started_by_user_id' => $actor->id,
                'status' => ReservationSessionStatus::Active,
                'metadata' => [
                    'scheduled_start_at' => $locked->start_at?->toIso8601String(),
                    'scheduled_end_at' => $locked->end_at?->toIso8601String(),
                    'scheduled_duration_minutes' => $locked->duration_minutes,
                ],
            ]);

            $updated = $this->changeStatus->execute(
                reservation: $locked,
                toStatus: ReservationStatus::CheckedIn,
                actor: $actor,
                source: $source,
                reason: null,
            );

            $updated->update([
                'check_in_method' => $method->value,
                'checked_in_by_user_id' => $actor->id,
            ]);

            $this->auditLog->execute(
                action: 'reservation.checked_in',
                entityType: 'reservation',
                entityId: $updated->id,
                actor: $actor,
                newValues: [
                    'status' => ReservationStatus::CheckedIn->value,
                    'check_in_method' => $method->value,
                ],
                metadata: ['business_id' => $updated->business_id, 'resource_id' => $updated->resource_id],
                request: $request,
            );

            $fresh = $updated->fresh(['business', 'resource.group', 'customer', 'activeSession']);

            DB::afterCommit(fn () => ReservationCheckedIn::dispatch($fresh, $actor, $method));

            return $fresh;
        });
    }
}
