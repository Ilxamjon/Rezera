<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\Enums\BusinessMemberInvitationStatus;
use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Subscriptions\SubscriptionFeature;
use App\Models\Business;
use App\Models\BusinessMemberInvitation;
use App\Models\User;
use App\Services\Businesses\BusinessStaffInvitationService;
use App\Services\Notifications\NotificationService;
use App\Services\Subscriptions\BusinessUsageService;
use App\Support\Phone\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InviteBusinessMemberAction
{
    public function __construct(
        private readonly BusinessStaffInvitationService $invitations,
        private readonly BusinessUsageService $usage,
        private readonly RecordBusinessStaffAuditAction $staffAudit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array{phone: string, member_role: string, job_title?: ?string}  $data
     */
    public function execute(Business $business, User $inviter, array $data, ?Request $request = null): BusinessMemberInvitation
    {
        $phone = PhoneNormalizer::normalize($data['phone']);
        $role = BusinessMemberRole::from($data['member_role']);
        $jobTitle = $data['job_title'] ?? null;

        $this->invitations->assertRoleInvitable($role);
        $this->invitations->assertNoDuplicatePending($business, $phone);
        $this->usage->assertCanConsume($business, SubscriptionFeature::STAFF_MAX);

        $targetUser = User::query()->where('phone', $phone)->first();

        if ($targetUser !== null) {
            $existingMember = $business->members()
                ->where('user_id', $targetUser->id)
                ->where('status', \App\Domain\Businesses\Enums\BusinessMemberStatus::Active)
                ->exists();

            if ($existingMember) {
                throw ValidationException::withMessages([
                    'phone' => [__('business.member_already_exists')],
                ]);
            }
        }

        return DB::transaction(function () use ($business, $inviter, $phone, $role, $jobTitle, $targetUser, $request): BusinessMemberInvitation {
            $invitation = BusinessMemberInvitation::query()->create([
                'business_id' => $business->id,
                'phone' => $phone,
                'invited_user_id' => $targetUser?->id,
                'invited_by_user_id' => $inviter->id,
                'member_role' => $role,
                'job_title' => $jobTitle,
                'status' => BusinessMemberInvitationStatus::Pending,
                'expires_at' => $this->invitations->expiryDate(),
            ]);

            $this->staffAudit->execute(
                action: 'business_staff.invited',
                business: $business,
                actor: $inviter,
                oldValues: null,
                newValues: [
                    'phone' => $phone,
                    'member_role' => $role->value,
                    'job_title' => $jobTitle,
                    'invitation_id' => $invitation->id,
                ],
                request: $request,
            );

            if ($targetUser !== null) {
                $this->notifications->notifyUser(
                    user: $targetUser,
                    type: NotificationType::BusinessStaffInvited,
                    entityKey: 'invitation:'.$invitation->id,
                    placeholders: [
                        'business_name' => $business->name,
                        'member_role' => $role->value,
                    ],
                    data: [
                        'business_id' => $business->id,
                        'invitation_id' => $invitation->id,
                        'member_role' => $role->value,
                    ],
                );
            }

            return $invitation->fresh(['business', 'invitedBy']);
        });
    }
}
