<?php

namespace App\Actions\Businesses;

use App\Actions\Platform\CreateAuditLogAction;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Businesses\Enums\VerificationRequestStatus;
use App\Events\Businesses\BusinessVerificationSubmitted;
use App\Exceptions\Businesses\BusinessVerificationException;
use App\Models\Business;
use App\Models\BusinessVerification;
use App\Models\User;
use App\Services\Businesses\BusinessOnboardingService;
use Illuminate\Support\Facades\DB;

final class SubmitBusinessVerificationAction
{
    public function __construct(
        private readonly BusinessOnboardingService $onboarding,
        private readonly CreateAuditLogAction $audit,
    ) {}

    public function execute(Business $business, User $actor, bool $resubmit = false): BusinessVerification
    {
        $business = $this->onboarding->sync($business);

        if (! $this->onboarding->isReadyForVerification($business)) {
            throw BusinessVerificationException::onboardingIncomplete(
                $this->onboarding->progress($business)['remaining_steps'],
            );
        }

        if ($business->verification_status === BusinessVerificationStatus::Pending) {
            throw BusinessVerificationException::alreadyPending();
        }

        if ($resubmit && ! $business->verification_status?->allowsResubmission()) {
            throw BusinessVerificationException::cannotResubmit();
        }

        if (! $resubmit && $business->verification_status === BusinessVerificationStatus::Verified) {
            throw BusinessVerificationException::cannotResubmit();
        }

        return DB::transaction(function () use ($business, $actor, $resubmit): BusinessVerification {
            Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();

            $existingPending = BusinessVerification::query()
                ->where('business_id', $business->id)
                ->where('status', VerificationRequestStatus::Pending)
                ->lockForUpdate()
                ->first();

            if ($existingPending !== null) {
                throw BusinessVerificationException::alreadyPending();
            }

            $verification = BusinessVerification::query()->create([
                'business_id' => $business->id,
                'status' => VerificationRequestStatus::Pending,
                'submitted_by_user_id' => $actor->id,
                'submitted_at' => now(),
            ]);

            $business->update([
                'verification_status' => BusinessVerificationStatus::Pending,
                'status' => BusinessStatus::PendingReview,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->audit->execute(
                action: $resubmit ? 'business.verification_resubmitted' : 'business.verification_submitted',
                entityType: 'business_verification',
                entityId: $verification->id,
                actor: $actor,
                newValues: [
                    'business_id' => $business->id,
                    'status' => VerificationRequestStatus::Pending->value,
                ],
            );

            DB::afterCommit(fn () => BusinessVerificationSubmitted::dispatch($verification->fresh(['business'])));

            return $verification->fresh(['business', 'submittedBy']);
        });
    }
}
