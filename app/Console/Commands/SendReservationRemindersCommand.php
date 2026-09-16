<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\Notifications\NotificationPayloadBuilder;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendReservationRemindersCommand extends Command
{
    protected $signature = 'reservations:send-reminders
                            {--minutes= : Minutes before start (default from config)}
                            {--window=5 : Lookahead window in minutes}';

    protected $description = 'Send upcoming reservation reminders to customers (in-app + push when enabled)';

    public function handle(
        NotificationService $notifications,
        NotificationPayloadBuilder $payloadBuilder,
    ): int {
        $minutesBefore = max(
            5,
            (int) ($this->option('minutes') ?: config('rezera.notifications.reminder_minutes_before', 60)),
        );
        $window = max(1, (int) $this->option('window'));

        $now = CarbonImmutable::now('UTC');
        $windowStart = $now->addMinutes($minutesBefore);
        $windowEnd = $windowStart->addMinutes($window);

        $query = Reservation::query()
            ->with(['business', 'resource', 'customer'])
            ->where('status', ReservationStatus::Confirmed)
            ->whereNotNull('customer_id')
            ->where('start_at', '>=', $windowStart)
            ->where('start_at', '<', $windowEnd);

        $sent = 0;

        $query->orderBy('start_at')->chunkById(100, function ($reservations) use (
            $notifications,
            $payloadBuilder,
            $minutesBefore,
            &$sent,
        ): void {
            foreach ($reservations as $reservation) {
                if ($reservation->customer === null) {
                    continue;
                }

                $placeholders = $payloadBuilder->reservationPlaceholders($reservation);
                $data = $payloadBuilder->reservationData($reservation);

                $notifications->notifyUser(
                    user: $reservation->customer,
                    type: NotificationType::ReservationReminder,
                    entityKey: 'reservation:'.$reservation->id.':reminder:'.$minutesBefore,
                    placeholders: $placeholders,
                    data: $data,
                    channels: [NotificationChannel::Database, NotificationChannel::Push],
                );
                $sent++;
            }
        });

        $this->info("Reservation reminders processed: {$sent}");

        return self::SUCCESS;
    }
}
