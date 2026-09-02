<?php

namespace App\Actions\Businesses;

use App\Models\Business;
use App\Services\Businesses\BusinessOnboardingService;

class UpdateBusinessAction
{
    public function __construct(
        private readonly BusinessOnboardingService $onboarding,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Business $business, array $data): Business
    {
        $business->fill($data);
        $business->save();

        return $this->onboarding->sync($business->fresh(['category', 'bookingPolicy']));
    }
}
