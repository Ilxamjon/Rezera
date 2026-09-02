<?php

namespace App\Services\Businesses;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Models\Business;

final class BusinessReadinessService
{
    public function __construct(
        private readonly BusinessOnboardingService $onboarding,
        private readonly BusinessVisibilityService $visibility,
    ) {}

    public function isReadyForReservations(Business $business): bool
    {
        if ($business->deleted_at !== null) {
            return false;
        }

        if (! in_array($business->status, [BusinessStatus::Approved, BusinessStatus::Draft, BusinessStatus::PendingReview], true)) {
            return false;
        }

        if (config('business_onboarding.reservation_requires_onboarding_complete', true)
            && ! $this->onboarding->isComplete($business)) {
            return false;
        }

        if (config('business_onboarding.reservation_requires_verification', true)
            && $business->verification_status !== BusinessVerificationStatus::Verified) {
            return false;
        }

        return $this->onboarding->isComplete($business);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Business $business): array
    {
        $ready = $this->isReadyForReservations($business);

        return [
            'ready_for_reservations' => $ready,
            'ready_for_verification' => $this->onboarding->isReadyForVerification($business),
            'publication' => $this->visibility->publicationState($business),
            'missing_requirements' => $this->missingRequirements($business),
        ];
    }

    /**
     * @return list<string>
     */
    public function missingRequirements(Business $business): array
    {
        $missing = [];

        if ($business->deleted_at !== null) {
            $missing[] = 'business_deleted';
        }

        if (in_array($business->status, [BusinessStatus::Suspended, BusinessStatus::Archived, BusinessStatus::Rejected], true)) {
            $missing[] = 'business_not_operational';
        }

        if (config('business_onboarding.reservation_requires_onboarding_complete', true)
            && ! $this->onboarding->isComplete($business)) {
            $missing = array_merge($missing, $this->onboarding->progress($business)['remaining_steps']);
        }

        if (config('business_onboarding.reservation_requires_verification', true)
            && $business->verification_status !== BusinessVerificationStatus::Verified) {
            $missing[] = 'verification_required';
        }

        return array_values(array_unique($missing));
    }
}
