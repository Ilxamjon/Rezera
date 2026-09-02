<?php

namespace App\Data\Reservations;

use App\Domain\Reservations\Enums\ConfirmationMode;

final readonly class EffectiveReservationSettings
{
    public function __construct(
        public int $minDurationMinutes,
        public int $maxDurationMinutes,
        public int $durationStepMinutes,
        public int $minAdvanceMinutes,
        public int $maxAdvanceDays,
        public int $cancellationDeadlineMinutes,
        public int $pendingExpiryMinutes,
        public int $checkInEarlyMinutes,
        public int $noShowGraceMinutes,
        public int $bufferMinutes,
        public bool $customerCanCancel,
        public bool $businessCanCancel,
        public bool $allowSameDayReservations,
        public bool $requireCustomerNote,
        public ?int $maxActiveReservationsPerCustomer,
        public ?int $maxDailyReservationsPerCustomer,
        public ConfirmationMode $confirmationMode,
    ) {}

    public function autoConfirmReservations(): bool
    {
        return $this->confirmationMode === ConfirmationMode::Instant;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'minimum_duration_minutes' => $this->minDurationMinutes,
            'maximum_duration_minutes' => $this->maxDurationMinutes,
            'reservation_step_minutes' => $this->durationStepMinutes,
            'minimum_advance_minutes' => $this->minAdvanceMinutes,
            'maximum_advance_days' => $this->maxAdvanceDays,
            'allow_same_day_reservations' => $this->allowSameDayReservations,
            'customer_can_cancel' => $this->customerCanCancel,
            'cancellation_deadline_minutes' => $this->cancellationDeadlineMinutes,
            'auto_confirm' => $this->autoConfirmReservations(),
            'no_show_grace_minutes' => $this->noShowGraceMinutes,
        ];
    }
}
