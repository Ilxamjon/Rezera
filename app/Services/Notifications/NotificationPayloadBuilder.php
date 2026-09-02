<?php

namespace App\Services\Notifications;

use App\Models\Payment;
use App\Models\Reservation;
use App\Support\Reservations\ReservationIntervalResolver;

final class NotificationPayloadBuilder
{
    public function __construct(
        private readonly ReservationIntervalResolver $intervalResolver,
    ) {}

    /**
     * @return array<string, string>
     */
    public function reservationPlaceholders(Reservation $reservation): array
    {
        $timezone = $reservation->business?->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $start = $reservation->start_at?->copy()->timezone($timezone);
        $end = $reservation->end_at?->copy()->timezone($timezone);

        return [
            'reservation_number' => (string) $reservation->reservation_number,
            'business_name' => (string) ($reservation->business?->name ?? ''),
            'resource_name' => (string) ($reservation->resource?->name ?? ''),
            'date' => $start?->format('Y-m-d') ?? '',
            'start_time' => $start?->format('H:i') ?? '',
            'end_time' => $end?->format('H:i') ?? '',
            'amount' => (string) $reservation->total_amount,
            'currency' => (string) $reservation->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reservationData(Reservation $reservation): array
    {
        return [
            'type' => 'reservation',
            'reservation_id' => $reservation->id,
            'reservation_number' => $reservation->reservation_number,
            'business_id' => $reservation->business_id,
            'resource_id' => $reservation->resource_id,
            'status' => $reservation->status?->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function paymentPlaceholders(Payment $payment): array
    {
        $reservation = $payment->reservation;

        return [
            'payment_number' => (string) $payment->payment_number,
            'reservation_number' => (string) ($reservation?->reservation_number ?? ''),
            'business_name' => (string) ($reservation?->business?->name ?? ''),
            'amount' => (string) $payment->amount,
            'currency' => (string) $payment->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentData(Payment $payment): array
    {
        return [
            'type' => 'payment',
            'payment_id' => $payment->id,
            'payment_number' => $payment->payment_number,
            'reservation_id' => $payment->reservation_id,
            'reservation_number' => $payment->reservation?->reservation_number,
            'business_id' => $payment->business_id,
            'status' => $payment->status?->value,
        ];
    }
}
