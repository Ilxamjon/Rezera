<?php

namespace App\Services\Businesses;

use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Businesses\Enums\OnboardingStatus;
use App\Models\Business;

final class BusinessVisibilityService
{
    public function isPubliclyDiscoverable(Business $business): bool
    {
        if ($business->deleted_at !== null) {
            return false;
        }

        if ($business->status !== BusinessStatus::Approved) {
            return false;
        }

        if ($business->is_publicly_listed !== true) {
            return false;
        }

        if (config('business_onboarding.public_requires_verification', true)
            && $business->verification_status !== BusinessVerificationStatus::Verified) {
            return false;
        }

        if (config('business_onboarding.public_requires_onboarding_complete', true)
            && $business->onboarding_status !== OnboardingStatus::Completed) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicationState(Business $business): array
    {
        return [
            'is_publicly_listed' => (bool) $business->is_publicly_listed,
            'business_status' => $business->status?->value,
            'verification_status' => $business->verification_status?->value,
            'onboarding_status' => $business->onboarding_status?->value,
            'is_publicly_discoverable' => $this->isPubliclyDiscoverable($business),
            'missing_requirements' => $this->missingPublicationRequirements($business),
        ];
    }

    /**
     * @return list<string>
     */
    private function missingPublicationRequirements(Business $business): array
    {
        $missing = [];

        if ($business->status !== BusinessStatus::Approved) {
            $missing[] = 'business_not_approved';
        }

        if ($business->is_publicly_listed !== true) {
            $missing[] = 'not_publicly_listed';
        }

        if (config('business_onboarding.public_requires_verification', true)
            && $business->verification_status !== BusinessVerificationStatus::Verified) {
            $missing[] = 'verification_required';
        }

        if (config('business_onboarding.public_requires_onboarding_complete', true)
            && $business->onboarding_status !== OnboardingStatus::Completed) {
            $missing[] = 'onboarding_incomplete';
        }

        return $missing;
    }
}
