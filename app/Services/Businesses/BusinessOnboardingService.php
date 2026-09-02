<?php

namespace App\Services\Businesses;

use App\Domain\Businesses\BusinessOnboardingStep;
use App\Domain\Businesses\Enums\OnboardingStatus;
use App\Domain\Resources\Enums\ResourceStatus;
use App\Events\Businesses\BusinessOnboardingCompleted;
use App\Models\Business;
use App\Models\BusinessHour;
use App\Models\PricingRule;
use App\Models\Resource;
use Illuminate\Support\Facades\DB;

final class BusinessOnboardingService
{
    /**
     * @var array<string, callable(Business): bool>|null
     */
    private ?array $stepCheckers = null;

    /**
     * @return array{
     *     status: string,
     *     percentage: int,
     *     completed_count: int,
     *     required_count: int,
     *     completed_steps: list<string>,
     *     remaining_steps: list<string>,
     *     next_step: ?string,
     *     steps: list<array{code: string, title: string, completed: bool, required: bool, sort_order: int}>
     * }
     */
    public function progress(Business $business): array
    {
        $steps = $this->stepDefinitions();
        $completed = [];
        $remaining = [];

        foreach ($steps as $step) {
            if ($this->isStepCompleted($business, $step['code'])) {
                $completed[] = $step['code'];
            } elseif ($step['required']) {
                $remaining[] = $step['code'];
            }
        }

        $requiredCount = count(array_filter($steps, static fn (array $step): bool => $step['required']));
        $completedRequired = count(array_filter(
            $completed,
            static fn (string $code): bool => in_array($code, BusinessOnboardingStep::requiredSteps(), true),
        ));
        $percentage = $requiredCount > 0 ? (int) round(($completedRequired / $requiredCount) * 100) : 0;

        $status = match (true) {
            $completedRequired === 0 => OnboardingStatus::NotStarted->value,
            $completedRequired >= $requiredCount => OnboardingStatus::Completed->value,
            default => OnboardingStatus::InProgress->value,
        };

        return [
            'status' => $status,
            'percentage' => $percentage,
            'completed_count' => $completedRequired,
            'required_count' => $requiredCount,
            'completed_steps' => $completed,
            'remaining_steps' => $remaining,
            'next_step' => $remaining[0] ?? null,
            'steps' => array_map(function (array $step) use ($business, $completed): array {
                return [
                    'code' => $step['code'],
                    'title' => __($step['title_key']),
                    'completed' => in_array($step['code'], $completed, true),
                    'required' => $step['required'],
                    'sort_order' => $step['sort_order'],
                ];
            }, $steps),
        ];
    }

    public function isComplete(Business $business): bool
    {
        return $this->progress($business)['status'] === OnboardingStatus::Completed->value;
    }

    public function isReadyForVerification(Business $business): bool
    {
        return $this->isComplete($business);
    }

    public function sync(Business $business): Business
    {
        $progress = $this->progress($business);
        $wasCompleted = $business->onboarding_status === OnboardingStatus::Completed;

        $updates = [
            'onboarding_status' => $progress['status'],
        ];

        if ($progress['status'] === OnboardingStatus::Completed->value) {
            $updates['onboarding_completed_at'] = $business->onboarding_completed_at ?? now();
        } else {
            $updates['onboarding_completed_at'] = null;
        }

        if ($business->onboarding_status?->value !== $progress['status']
            || ($progress['status'] === OnboardingStatus::Completed->value && $business->onboarding_completed_at === null)) {
            $business->update($updates);
            $business = $business->fresh();

            if (! $wasCompleted && $progress['status'] === OnboardingStatus::Completed->value) {
                DB::afterCommit(fn () => BusinessOnboardingCompleted::dispatch($business));
            }
        }

        return $business;
    }

    /**
     * @return list<array{code: string, title_key: string, required: bool, sort_order: int}>
     */
    public function stepDefinitions(): array
    {
        return [
            ['code' => BusinessOnboardingStep::BUSINESS_PROFILE, 'title_key' => 'onboarding.steps.business_profile', 'required' => true, 'sort_order' => 10],
            ['code' => BusinessOnboardingStep::LOCATION, 'title_key' => 'onboarding.steps.location', 'required' => true, 'sort_order' => 20],
            ['code' => BusinessOnboardingStep::CONTACT_INFORMATION, 'title_key' => 'onboarding.steps.contact_information', 'required' => true, 'sort_order' => 30],
            ['code' => BusinessOnboardingStep::WORKING_HOURS, 'title_key' => 'onboarding.steps.working_hours', 'required' => true, 'sort_order' => 40],
            ['code' => BusinessOnboardingStep::RESOURCE_SETUP, 'title_key' => 'onboarding.steps.resource_setup', 'required' => true, 'sort_order' => 50],
            ['code' => BusinessOnboardingStep::PRICING_SETUP, 'title_key' => 'onboarding.steps.pricing_setup', 'required' => true, 'sort_order' => 60],
            ['code' => BusinessOnboardingStep::RESERVATION_SETTINGS, 'title_key' => 'onboarding.steps.reservation_settings', 'required' => true, 'sort_order' => 70],
        ];
    }

    private function isStepCompleted(Business $business, string $code): bool
    {
        return ($this->stepCheckers()[$code])($business);
    }

    /**
     * @return array<string, callable(Business): bool>
     */
    private function stepCheckers(): array
    {
        if ($this->stepCheckers !== null) {
            return $this->stepCheckers;
        }

        return $this->stepCheckers = [
            BusinessOnboardingStep::BUSINESS_PROFILE => static fn (Business $business): bool => filled($business->name)
                && $business->category_id !== null
                && filled($business->description),
            BusinessOnboardingStep::LOCATION => static fn (Business $business): bool => filled($business->city)
                && filled($business->address_line)
                && $business->latitude !== null
                && $business->longitude !== null
                && filled($business->timezone),
            BusinessOnboardingStep::CONTACT_INFORMATION => static fn (Business $business): bool => filled($business->phone) || filled($business->email),
            BusinessOnboardingStep::WORKING_HOURS => function (Business $business): bool {
                $hoursCount = BusinessHour::query()->where('business_id', $business->id)->count();
                if ($hoursCount !== 7) {
                    return false;
                }

                return BusinessHour::query()
                    ->where('business_id', $business->id)
                    ->where(function ($query): void {
                        $query->where('is_open_24h', true)
                            ->orWhere(function ($openQuery): void {
                                $openQuery->where('is_closed', false)
                                    ->whereNotNull('opens_at')
                                    ->whereNotNull('closes_at');
                            });
                    })
                    ->exists();
            },
            BusinessOnboardingStep::RESOURCE_SETUP => static fn (Business $business): bool => Resource::query()
                ->where('business_id', $business->id)
                ->where('status', ResourceStatus::Active)
                ->whereNull('deleted_at')
                ->exists(),
            BusinessOnboardingStep::PRICING_SETUP => function (Business $business): bool {
                $hasPricedResource = Resource::query()
                    ->where('business_id', $business->id)
                    ->where('status', ResourceStatus::Active)
                    ->whereNull('deleted_at')
                    ->where('hourly_rate_amount', '>', 0)
                    ->exists();

                if ($hasPricedResource) {
                    return true;
                }

                return PricingRule::query()
                    ->where('business_id', $business->id)
                    ->where('is_active', true)
                    ->exists();
            },
            BusinessOnboardingStep::RESERVATION_SETTINGS => static fn (Business $business): bool => $business->bookingPolicy()->exists(),
        ];
    }
}
