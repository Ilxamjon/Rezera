<?php

namespace App\Listeners\Subscriptions;

use App\Actions\Subscriptions\FulfillSubscriptionPaymentAction;
use App\Events\Payments\PaymentSucceeded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class ActivateSubscriptionOnPaymentSucceeded implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function __construct(
        private readonly FulfillSubscriptionPaymentAction $fulfillSubscriptionPayment,
    ) {}

    public function handle(PaymentSucceeded $event): void
    {
        $intent = $event->payment->metadata['subscription_intent'] ?? null;

        if (! is_array($intent) || ($event->payment->metadata['purpose'] ?? null) !== 'subscription') {
            return;
        }

        $this->fulfillSubscriptionPayment->execute($event->payment);
    }
}
