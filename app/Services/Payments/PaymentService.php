<?php

namespace App\Services\Payments;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Reservations\Enums\PaymentMethod as ReservationPaymentMethod;
use App\Domain\Reservations\Enums\PaymentStatus as ReservationPaymentStatus;
use App\Domain\Reservations\Enums\ReservationEventSource;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Events\Payments\PaymentCancelled;
use App\Events\Payments\PaymentCreated;
use App\Events\Payments\PaymentFailed;
use App\Events\Payments\PaymentProcessing;
use App\Events\Payments\PaymentRefunded;
use App\Events\Payments\PaymentSucceeded;
use App\Exceptions\Payments\PaymentException;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Actions\Reservations\ChangeReservationStatusAction;
use Illuminate\Support\Facades\DB;

final class PaymentService
{
    public function __construct(
        private readonly PaymentProviderResolver $providerResolver,
        private readonly PaymentTransitionService $transitionService,
        private readonly ChangeReservationStatusAction $changeReservationStatus,
    ) {}

    public function createPayment(User $customer, Reservation $reservation, PaymentProvider $provider): Payment
    {
        $this->assertReservationPayable($reservation, $customer);

        $gateway = $this->providerResolver->resolve($provider);

        try {
            return DB::transaction(function () use ($customer, $reservation, $provider, $gateway): Payment {
                $lockedReservation = Reservation::query()
                    ->whereKey($reservation->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = Payment::query()
                    ->where('reservation_id', $lockedReservation->id)
                    ->whereIn('status', array_map(
                        static fn (PaymentStatus $status): string => $status->value,
                        PaymentStatus::active(),
                    ))
                    ->first();

                if ($existing !== null) {
                    return $existing->load(['reservation', 'business']);
                }

                $amount = (int) $lockedReservation->total_amount;
                $currency = $lockedReservation->currency ?? config('payment.default_currency', 'UZS');

                $payment = Payment::query()->create([
                    'business_id' => $lockedReservation->business_id,
                    'reservation_id' => $lockedReservation->id,
                    'user_id' => $customer->id,
                    'payment_number' => app(PaymentNumberGenerator::class)->generate(),
                    'provider' => $provider,
                    'status' => PaymentStatus::Pending,
                    'payment_method' => $provider->value,
                    'amount' => $amount,
                    'currency' => $currency,
                    'description' => __('payments.description', [
                        'number' => $lockedReservation->reservation_number,
                    ]),
                ]);

                $result = $gateway->createPayment($payment);

                if (! $result->success) {
                    throw PaymentException::providerError($result->failureReason ?? __('payments.provider_create_failed'));
                }

                $payment->update([
                    'provider_payment_id' => $result->providerPaymentId,
                    'metadata' => $result->metadata,
                ]);

                DB::afterCommit(fn () => PaymentCreated::dispatch($payment->fresh()));

                return $payment->fresh(['reservation', 'business']);
            });
        } catch (\Illuminate\Database\QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                $existing = Payment::query()
                    ->where('reservation_id', $reservation->id)
                    ->whereIn('status', ['pending', 'processing'])
                    ->first();

                if ($existing !== null) {
                    return $existing->load(['reservation', 'business']);
                }

                throw PaymentException::alreadyActive();
            }

            throw $exception;
        }
    }

    public function applyGatewayResult(Payment $payment, \App\Data\Payments\PaymentGatewayResult $result): Payment
    {
        return DB::transaction(function () use ($payment, $result): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $payment->status;

            if ($fromStatus === null || $result->status === null) {
                throw PaymentException::invalidState(__('payments.invalid_state'));
            }

            if ($fromStatus->isTerminal() && $fromStatus === $result->status) {
                return $payment->load(['reservation', 'business']);
            }

            $this->transitionService->assertCanTransition($fromStatus, $result->status);

            $updates = [
                'status' => $result->status,
                'metadata' => array_merge($payment->metadata ?? [], $result->metadata),
            ];

            if ($result->providerPaymentId !== null) {
                $updates['provider_payment_id'] = $result->providerPaymentId;
            }

            if ($result->status === PaymentStatus::Processing) {
                $updates['metadata']['processing_at'] = now()->toIso8601String();
            }

            if ($result->status === PaymentStatus::Paid) {
                $updates['paid_at'] = now();
            }

            if ($result->status === PaymentStatus::Failed) {
                $updates['failed_at'] = now();
                $updates['failure_reason'] = $result->failureReason;
            }

            if ($result->status === PaymentStatus::Cancelled) {
                $updates['cancelled_at'] = now();
            }

            if (in_array($result->status, [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded], true)) {
                $updates['refunded_at'] = now();
            }

            $payment->update($updates);

            if ($result->status === PaymentStatus::Paid) {
                $this->applyReservationPaymentSuccess($payment);
            }

            $fresh = $payment->fresh(['reservation', 'business']);

            DB::afterCommit(function () use ($fresh, $fromStatus, $result): void {
                match ($result->status) {
                    PaymentStatus::Processing => PaymentProcessing::dispatch($fresh, $fromStatus),
                    PaymentStatus::Paid => PaymentSucceeded::dispatch($fresh, $fromStatus),
                    PaymentStatus::Failed => PaymentFailed::dispatch($fresh, $fromStatus),
                    PaymentStatus::Cancelled => PaymentCancelled::dispatch($fresh, $fromStatus),
                    PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded => PaymentRefunded::dispatch($fresh, $fromStatus),
                    default => null,
                };
            });

            return $fresh;
        });
    }

    public function synchronizeStatus(Payment $payment): Payment
    {
        if ($payment->status?->isTerminal()) {
            return $payment->load(['reservation', 'business']);
        }

        $gateway = $this->providerResolver->resolve($payment->provider);
        $statusResult = $gateway->getPaymentStatus($payment);

        return $this->applyGatewayResult($payment, new \App\Data\Payments\PaymentGatewayResult(
            success: true,
            provider: $statusResult->provider,
            providerPaymentId: $statusResult->providerPaymentId,
            status: $statusResult->status,
            failureReason: $statusResult->failureReason,
            metadata: $statusResult->metadata,
        ));
    }

    private function applyReservationPaymentSuccess(Payment $payment): void
    {
        if ($payment->reservation_id === null) {
            return;
        }

        $reservation = Reservation::query()
            ->whereKey($payment->reservation_id)
            ->lockForUpdate()
            ->firstOrFail();

        $reservation->update([
            'payment_status' => ReservationPaymentStatus::Paid,
            'payment_method' => $this->mapProviderToReservationMethod($payment->provider),
        ]);

        if ($reservation->status === ReservationStatus::Pending) {
            $this->changeReservationStatus->execute(
                reservation: $reservation,
                toStatus: ReservationStatus::Confirmed,
                actor: $payment->user,
                source: ReservationEventSource::SystemJob,
                reason: __('payments.auto_confirm_after_payment'),
            );
        }
    }

    private function mapProviderToReservationMethod(PaymentProvider $provider): ReservationPaymentMethod
    {
        return match ($provider) {
            PaymentProvider::Mock => ReservationPaymentMethod::Mock,
            default => ReservationPaymentMethod::Venue,
        };
    }

    private function assertReservationPayable(Reservation $reservation, User $customer): void
    {
        if ($reservation->customer_id !== $customer->id) {
            throw PaymentException::notAllowed(__('payments.not_owner'));
        }

        if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
            throw PaymentException::notAllowed(__('payments.reservation_not_payable'));
        }

        if ($reservation->payment_status === ReservationPaymentStatus::Paid) {
            throw PaymentException::notAllowed(__('payments.reservation_already_paid'));
        }

        if ((int) $reservation->total_amount <= 0) {
            throw PaymentException::notAllowed(__('payments.zero_amount'));
        }
    }
}
