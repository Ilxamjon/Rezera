<?php

namespace App\Console\Commands;

use App\Actions\Businesses\ApproveBusinessVerificationAction;
use App\Domain\Businesses\Enums\BusinessStatus;
use App\Domain\Businesses\Enums\BusinessVerificationStatus;
use App\Domain\Businesses\Enums\OnboardingStatus;
use App\Domain\Businesses\Enums\VerificationRequestStatus;
use App\Models\Business;
use App\Models\BusinessVerification;
use App\Models\User;
use App\Services\Businesses\BusinessOnboardingService;
use Illuminate\Console\Command;

class PublishReadyBusinessesCommand extends Command
{
    protected $signature = 'rezera:publish-ready-businesses {--dry-run : List only}';

    protected $description = 'Approve & publicly list businesses that finished onboarding (beta/dev helper)';

    public function handle(
        BusinessOnboardingService $onboarding,
        ApproveBusinessVerificationAction $approve,
    ): int {
        $dry = (bool) $this->option('dry-run');
        $actor = User::query()
            ->whereIn('platform_role', ['super_admin', 'platform_admin', 'admin'])
            ->first()
            ?? User::query()->first();

        if ($actor === null) {
            $this->error('No user found to act as approver.');

            return self::FAILURE;
        }

        $businesses = Business::query()
            ->whereIn('status', [
                BusinessStatus::Draft->value,
                BusinessStatus::PendingReview->value,
                BusinessStatus::Approved->value,
            ])
            ->whereNull('deleted_at')
            ->get();

        $published = 0;

        foreach ($businesses as $business) {
            $business = $onboarding->sync($business);

            if (! $onboarding->isComplete($business)) {
                $this->line("skip incomplete: {$business->name} ({$business->id})");

                continue;
            }

            if (
                $business->status === BusinessStatus::Approved
                && $business->verification_status === BusinessVerificationStatus::Verified
                && $business->is_publicly_listed
                && $business->onboarding_status === OnboardingStatus::Completed
            ) {
                continue;
            }

            $this->info(($dry ? '[dry] ' : '')."publish: {$business->name} ({$business->id})");

            if ($dry) {
                $published++;

                continue;
            }

            $pending = BusinessVerification::query()
                ->where('business_id', $business->id)
                ->where('status', VerificationRequestStatus::Pending)
                ->latest('submitted_at')
                ->first();

            if ($pending === null) {
                $pending = BusinessVerification::query()->create([
                    'business_id' => $business->id,
                    'status' => VerificationRequestStatus::Pending,
                    'submitted_by_user_id' => $actor->id,
                    'submitted_at' => now(),
                ]);
                $business->update([
                    'verification_status' => BusinessVerificationStatus::Pending,
                    'status' => BusinessStatus::PendingReview,
                    'submitted_at' => $business->submitted_at ?? now(),
                ]);
            }

            $approve->execute($pending->fresh(), $actor);
            $published++;
        }

        $this->info("Done. Published: {$published}");

        return self::SUCCESS;
    }
}
