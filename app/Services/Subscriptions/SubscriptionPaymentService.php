<?php

namespace App\Services\Subscriptions;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Subscriptions\Enums\BillingInterval;
use App\Events\Payments\PaymentCreated;
use App\Exceptions\Payments\PaymentException;
use App\Models\Business;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payments\PaymentNumberGenerator;
use App\Services\Payments\PaymentProviderResolver;
use Illuminate\Support\Facades\DB;

final class SubscriptionPaymentService
{
    public function __construct(
        private readonly PaymentProviderResolver $providerResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $intent
     */
    public function createSubscriptionPayment(
        User $payer,
        Business $business,
        SubscriptionPlan $plan,
        BillingInterval $interval,
        array $intent,
        PaymentProvider $provider = PaymentProvider::Mock,
    ): Payment {
        $amount = $plan->priceForInterval($interval->value);

        if ($amount <= 0) {
            throw PaymentException::notAllowed(__('subscriptions.no_payment_required'));
        }

        $gateway = $this->providerResolver->resolve($provider);

        return DB::transaction(function () use ($payer, $business, $plan, $interval, $intent, $provider, $amount, $gateway): Payment {
            $payment = Payment::query()->create([
                'business_id' => $business->id,
                'reservation_id' => null,
                'business_subscription_id' => null,
                'user_id' => $payer->id,
                'payment_number' => app(PaymentNumberGenerator::class)->generate(),
                'provider' => $provider,
                'status' => PaymentStatus::Pending,
                'payment_method' => $provider->value,
                'amount' => $amount,
                'currency' => $plan->currency,
                'description' => __('subscriptions.payment_description', [
                    'plan' => $plan->localizedName(),
                    'interval' => __('subscriptions.intervals.'.$interval->value),
                ]),
                'metadata' => [
                    'purpose' => 'subscription',
                    'subscription_intent' => array_merge($intent, [
                        'plan_id' => $plan->id,
                        'plan_code' => $plan->code,
                        'billing_interval' => $interval->value,
                        'business_id' => $business->id,
                    ]),
                ],
            ]);

            $result = $gateway->createPayment($payment);

            if (! $result->success) {
                throw PaymentException::providerError($result->failureReason ?? __('payments.provider_create_failed'));
            }

            $payment->update([
                'provider_payment_id' => $result->providerPaymentId,
                'metadata' => array_merge($payment->metadata ?? [], $result->metadata ?? []),
            ]);

            DB::afterCommit(fn () => PaymentCreated::dispatch($payment->fresh()));

            return $payment->fresh(['business']);
        });
    }
}
