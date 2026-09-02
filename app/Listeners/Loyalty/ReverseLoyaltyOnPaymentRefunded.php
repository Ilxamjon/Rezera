<?php

namespace App\Listeners\Loyalty;

use App\Events\Payments\PaymentRefunded;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class ReverseLoyaltyOnPaymentRefunded implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function __construct(
        private readonly LoyaltyService $loyaltyService,
    ) {}

    public function handle(PaymentRefunded $event): void
    {
        $this->loyaltyService->reverseFromRefund($event->payment);
    }
}
