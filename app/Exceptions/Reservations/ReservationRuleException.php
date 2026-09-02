<?php

namespace App\Exceptions\Reservations;

use RuntimeException;

final class ReservationRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function durationTooShort(int $minimum): self
    {
        return new self(__('reservation_rules.duration_too_short', ['minimum' => $minimum]), 'reservation_duration_too_short', ['minimum' => $minimum]);
    }

    public static function durationTooLong(int $maximum): self
    {
        return new self(__('reservation_rules.duration_too_long', ['maximum' => $maximum]), 'reservation_duration_too_long', ['maximum' => $maximum]);
    }

    public static function invalidStep(int $step): self
    {
        return new self(__('reservation_rules.invalid_step', ['step' => $step]), 'invalid_reservation_step', ['step' => $step]);
    }

    public static function tooSoon(int $minutes): self
    {
        return new self(__('reservation_rules.too_soon', ['minutes' => $minutes]), 'reservation_too_soon', ['minutes' => $minutes]);
    }

    public static function tooFar(int $days): self
    {
        return new self(__('reservation_rules.too_far', ['days' => $days]), 'reservation_too_far_in_future', ['days' => $days]);
    }

    public static function sameDayDisabled(): self
    {
        return new self(__('reservation_rules.same_day_disabled'), 'same_day_reservations_disabled');
    }

    public static function inPast(): self
    {
        return new self(__('reservation_rules.in_past'), 'reservation_in_past');
    }

    public static function customerLimitReached(string $type, int $limit): self
    {
        return new self(__('reservation_rules.customer_limit_reached'), 'customer_reservation_limit_reached', ['type' => $type, 'limit' => $limit]);
    }

    public static function customerCancellationNotAllowed(): self
    {
        return new self(__('reservation_rules.customer_cancellation_not_allowed'), 'customer_cancellation_not_allowed');
    }

    public static function cancellationDeadlinePassed(): self
    {
        return new self(__('reservation_rules.cancellation_deadline_passed'), 'cancellation_deadline_passed');
    }

    public static function businessCancellationNotAllowed(): self
    {
        return new self(__('reservation_rules.business_cancellation_not_allowed'), 'business_cancellation_not_allowed');
    }

    public static function noteRequired(): self
    {
        return new self(__('reservation_rules.note_required'), 'reservation_note_required');
    }

    public static function invalidStartTime(): self
    {
        return new self(__('reservation_rules.invalid_start_time'), 'reservation_start_time_invalid');
    }
}
