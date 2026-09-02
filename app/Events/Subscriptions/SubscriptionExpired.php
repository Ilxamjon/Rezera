<?php

namespace App\Events\Subscriptions;

use App\Models\BusinessSubscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SubscriptionExpired
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly BusinessSubscription $subscription) {}
}
