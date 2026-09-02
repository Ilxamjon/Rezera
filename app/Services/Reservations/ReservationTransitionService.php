<?php

namespace App\Services\Reservations;

use App\Domain\Reservations\Enums\ReservationStatus;
use Illuminate\Validation\ValidationException;

final class ReservationTransitionService
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'rejected', 'cancelled', 'expired'],
        'confirmed' => ['cancelled', 'completed', 'checked_in', 'no_show'],
        'checked_in' => ['completed', 'no_show'],
        'rejected' => [],
        'cancelled' => [],
        'expired' => [],
        'completed' => [],
        'no_show' => [],
    ];

    public function assertCanTransition(ReservationStatus $from, ReservationStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => [__('reservations.invalid_status_transition', [
                    'from' => $from->value,
                    'to' => $to->value,
                ])],
            ]);
        }
    }

    public function canTransition(ReservationStatus $from, ReservationStatus $to): bool
    {
        if ($from === $to) {
            return false;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    public function isCancellableByCustomer(ReservationStatus $status): bool
    {
        return in_array($status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true);
    }
}
