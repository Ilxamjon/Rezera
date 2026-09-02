<?php

namespace App\Listeners\Notifications;

use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Notifications\Enums\NotificationType;
use App\Events\Businesses\BusinessBecameReservationReady;
use App\Events\Businesses\BusinessOnboardingCompleted;
use App\Events\Businesses\BusinessVerificationApproved;
use App\Events\Businesses\BusinessVerificationRejected;
use App\Events\Businesses\BusinessVerificationSubmitted;
use App\Models\User;
use App\Services\Authorization\BusinessAuthorizationService;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class DispatchBusinessVerificationNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    public bool $afterCommit = true;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly BusinessAuthorizationService $authorization,
    ) {}

    public function handleSubmitted(BusinessVerificationSubmitted $event): void
    {
        $verification = $event->verification->loadMissing(['business', 'submittedBy']);
        $business = $verification->business;

        if ($business === null) {
            return;
        }

        foreach ($this->platformManagers() as $admin) {
            $this->notifications->notifyUser(
                user: $admin,
                type: NotificationType::BusinessVerificationSubmitted,
                entityKey: 'business_verification:'.$verification->id.':submitted',
                placeholders: ['business_name' => $business->name],
                data: [
                    'verification_id' => $verification->id,
                    'business_id' => $business->id,
                ],
            );
        }
    }

    public function handleApproved(BusinessVerificationApproved $event): void
    {
        $this->notifyBusinessManagers($event->verification, NotificationType::BusinessVerificationApproved);
    }

    public function handleRejected(BusinessVerificationRejected $event): void
    {
        $this->notifyBusinessManagers($event->verification, NotificationType::BusinessVerificationRejected);
    }

    public function handleOnboardingCompleted(BusinessOnboardingCompleted $event): void
    {
        $business = $event->business;

        foreach ($this->businessManagers($business->id) as $manager) {
            $this->notifications->notifyUser(
                user: $manager,
                type: NotificationType::BusinessOnboardingCompleted,
                entityKey: 'business:'.$business->id.':onboarding_completed',
                placeholders: ['business_name' => $business->name],
                data: ['business_id' => $business->id],
            );
        }
    }

    public function handleReservationReady(BusinessBecameReservationReady $event): void
    {
        $business = $event->business;

        foreach ($this->businessManagers($business->id) as $manager) {
            $this->notifications->notifyUser(
                user: $manager,
                type: NotificationType::BusinessReadyForReservations,
                entityKey: 'business:'.$business->id.':reservation_ready',
                placeholders: ['business_name' => $business->name],
                data: ['business_id' => $business->id],
            );
        }
    }

    private function notifyBusinessManagers($verification, NotificationType $type): void
    {
        $verification = $verification->loadMissing(['business']);
        $business = $verification->business;

        if ($business === null) {
            return;
        }

        foreach ($this->businessManagers($business->id) as $manager) {
            $this->notifications->notifyUser(
                user: $manager,
                type: $type,
                entityKey: 'business_verification:'.$verification->id.':'.$type->value,
                placeholders: ['business_name' => $business->name],
                data: [
                    'verification_id' => $verification->id,
                    'business_id' => $business->id,
                ],
            );
        }
    }

    /**
     * @return iterable<int, User>
     */
    private function platformManagers(): iterable
    {
        return User::query()
            ->whereIn('platform_role', [PlatformRole::SuperAdmin, PlatformRole::PlatformAdmin, PlatformRole::Admin])
            ->get();
    }

    /**
     * @return iterable<int, User>
     */
    private function businessManagers(string $businessId): iterable
    {
        $members = \App\Models\BusinessMember::query()
            ->where('business_id', $businessId)
            ->where('status', 'active')
            ->with('user')
            ->get();

        foreach ($members as $member) {
            if ($member->user !== null && $this->authorization->canManageBusiness($member->user, $businessId)) {
                yield $member->user;
            }
        }
    }
}
