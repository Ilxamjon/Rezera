<?php

namespace App\Events\Reservations;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReservationStatusChanged
{
  use Dispatchable;
  use SerializesModels;

  public function __construct(
    public readonly Reservation $reservation,
    public readonly ?ReservationStatus $fromStatus,
    public readonly ReservationStatus $toStatus,
    public readonly ?User $actor,
  ) {}
}
