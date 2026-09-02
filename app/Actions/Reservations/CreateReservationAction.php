<?php

namespace App\Actions\Reservations;

use App\Domain\Availability\Enums\AvailabilityReason;
use App\Domain\Reservations\Enums\ConfirmationMode;
use App\Domain\Reservations\Enums\PaymentMethod;
use App\Domain\Reservations\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Exceptions\ReservationConflictException;
use App\Events\Reservations\ReservationCreated;
use App\Models\Business;
use App\Models\Reservation;
use App\Models\Resource;
use App\Models\User;
use App\Services\Availability\AvailabilityEngine;
use App\Services\Availability\AvailabilityQuery;
use App\Models\PromoCode;
use App\Services\Promotions\DiscountEngine;
use App\Services\Reservations\ReservationConflictService;
use App\Services\Reservations\ReservationNumberGenerator;
use App\Services\Reservations\ReservationRulesEngine;
use App\Support\Reservations\OccupancyRange;
use App\Support\Reservations\ReservationIntervalResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateReservationAction
{
    public function __construct(
        private readonly ReservationIntervalResolver $intervalResolver,
        private readonly AvailabilityEngine $availabilityEngine,
        private readonly ReservationConflictService $conflictService,
        private readonly DiscountEngine $discountEngine,
        private readonly ReservationNumberGenerator $numberGenerator,
        private readonly ReservationRulesEngine $rulesEngine,
        private readonly RecordReservationEventAction $recordEvent,
    ) {}

    /**
     * @param  array{resource_id: string, date: string, start_time: string, end_time: string, notes?: ?string, promo_code?: ?string}  $data
     */
    public function execute(
        User $customer,
        Business $business,
        array $data,
        ?string $idempotencyKey = null,
    ): Reservation {
        if ($idempotencyKey !== null) {
            $existing = Reservation::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('customer_id', $customer->id)
                ->first();

            if ($existing !== null) {
                return $existing->load(['business', 'resource.group']);
            }
        }

        if (! $business->isPubliclyVisible()) {
            throw ValidationException::withMessages([
                'business' => [__('reservations.business_not_bookable')],
            ]);
        }

        $timezone = $business->timezone ?? config('rezera.default_business_timezone', 'Asia/Tashkent');
        $date = CarbonImmutable::parse($data['date'], $timezone)->startOfDay();
        $interval = $this->intervalResolver->resolve($date, $data['start_time'], $data['end_time'], $timezone);
        $utc = $this->intervalResolver->toUtcPayload($interval);

        $this->assertPreTransactionRules($customer, $business, $data['resource_id'], $date, $data['start_time'], $data['end_time']);

        $resource = Resource::query()
            ->where('id', $data['resource_id'])
            ->where('business_id', $business->id)
            ->firstOrFail();

        $this->rulesEngine->validateCreation(
            business: $business,
            resource: $resource,
            customer: $customer,
            interval: $interval,
            timezone: $timezone,
            data: $data,
        );

        try {
            return DB::transaction(function () use (
                $customer,
                $business,
                $data,
                $idempotencyKey,
                $interval,
                $utc,
                $timezone,
            ): Reservation {
                /** @var Resource $resource */
                $resource = Resource::query()
                    ->where('id', $data['resource_id'])
                    ->where('business_id', $business->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertResourceBookable($resource);

                $settings = $this->rulesEngine->getEffectiveSettings($business, $resource);
                $this->rulesEngine->validateCustomerLimits($business, $customer, $settings, $interval->start, $timezone);

                if ($this->conflictService->hasConflictIncludingBuffer(
                    $resource->id,
                    $utc['start_at'],
                    $utc['end_at'],
                    $settings->bufferMinutes,
                )) {
                    throw new ReservationConflictException(__('reservations.conflict'));
                }

                $availability = $this->availabilityEngine->calculate(new AvailabilityQuery(
                    business: $business->fresh(['hours']),
                    date: CarbonImmutable::parse($data['date'], $timezone)->startOfDay(),
                    startTime: $data['start_time'],
                    endTime: $data['end_time'],
                    resourceId: $resource->id,
                    managementView: true,
                    includeUnavailable: true,
                ));

                $resourceResult = $availability->resources[0] ?? null;

                if ($resourceResult === null || $resourceResult->reason !== AvailabilityReason::Available) {
                    throw ValidationException::withMessages([
                        'start_time' => [__('reservations.not_available', [
                            'reason' => $resourceResult?->reason->value ?? $availability->requestLevelReason?->value ?? 'unavailable',
                        ])],
                    ]);
                }

                $bufferMinutes = $settings->bufferMinutes;
                $confirmationMode = $settings->confirmationMode;
                $status = $confirmationMode === ConfirmationMode::Manual
                    ? ReservationStatus::Pending
                    : ReservationStatus::Confirmed;

                $pricing = $this->discountEngine->resolveForReservation(
                    user: $customer,
                    business: $business,
                    resource: $resource,
                    startAtUtc: $utc['start_at'],
                    endAtUtc: $utc['end_at'],
                    timezone: $timezone,
                    code: $data['promo_code'] ?? null,
                );

                $reservation = Reservation::query()->create([
                    'customer_id' => $customer->id,
                    'customer_name_snapshot' => $customer->name,
                    'customer_phone_snapshot' => $customer->phone,
                    'business_id' => $business->id,
                    'resource_id' => $resource->id,
                    'reservation_number' => $this->numberGenerator->generate(),
                    'start_at' => $utc['start_at'],
                    'end_at' => $utc['end_at'],
                    'duration_minutes' => $utc['duration_minutes'],
                    'buffer_minutes_applied' => $bufferMinutes,
                    'occupancy_range' => OccupancyRange::expression(
                        $utc['start_at'],
                        $utc['end_at'],
                        $bufferMinutes,
                    ),
                    'status' => $status,
                    'hourly_rate_amount' => $pricing['hourly_rate_amount'],
                    'subtotal_amount' => $pricing['subtotal_amount'],
                    'discount_amount' => $pricing['discount_amount'],
                    'total_amount' => $pricing['total_amount'],
                    'currency' => $pricing['currency'],
                    'promo_code_id' => $pricing['promo_code_id'] ?? null,
                    'promo_code_snapshot' => $pricing['promo_code_snapshot'] ?? null,
                    'discount_type' => $pricing['discount_type'] ?? null,
                    'discount_value_snapshot' => $pricing['discount_value_snapshot'] ?? null,
                    'pricing_snapshot' => $pricing['pricing_snapshot'] ?? null,
                    'confirmation_mode' => $confirmationMode,
                    'payment_status' => PaymentStatus::PayAtVenue,
                    'payment_method' => PaymentMethod::Venue,
                    'notes' => $data['notes'] ?? null,
                    'expires_at' => $status === ReservationStatus::Pending
                        ? now()->addMinutes($settings->pendingExpiryMinutes)
                        : null,
                    'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
                ]);

                if ($pricing['promo_code_id'] !== null) {
                    $promo = PromoCode::query()->findOrFail($pricing['promo_code_id']);

                    $this->discountEngine->redeem(
                        promo: $promo,
                        user: $customer,
                        reservationId: $reservation->id,
                        discountAmount: $pricing['discount_amount'],
                        currency: $pricing['currency'],
                    );
                }

                $this->recordEvent->execute(
                    reservation: $reservation,
                    fromStatus: null,
                    toStatus: $status,
                    actor: $customer,
                    source: ReservationEventSource::ApiCustomer,
                );

                $fresh = $reservation->fresh(['business', 'resource.group', 'customer']);

                DB::afterCommit(fn () => ReservationCreated::dispatch($fresh));

                return $fresh;
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23P01') {
                throw new ReservationConflictException(__('reservations.conflict'), previous: $exception);
            }

            throw $exception;
        }
    }

    private function assertPreTransactionRules(
        User $customer,
        Business $business,
        string $resourceId,
        CarbonImmutable $date,
        string $startTime,
        string $endTime,
    ): void {
        unset($customer);

        $belongs = Resource::query()
            ->where('id', $resourceId)
            ->where('business_id', $business->id)
            ->whereNull('deleted_at')
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'resource_id' => [__('reservations.resource_not_in_business')],
            ]);
        }
    }

    private function assertResourceBookable(Resource $resource): void
    {
        if (! $resource->status?->isBookable()) {
            throw ValidationException::withMessages([
                'resource_id' => [__('reservations.resource_not_bookable')],
            ]);
        }

        if ($resource->hourly_rate_amount === null) {
            throw ValidationException::withMessages([
                'resource_id' => [__('reservations.resource_missing_price')],
            ]);
        }
    }
}
