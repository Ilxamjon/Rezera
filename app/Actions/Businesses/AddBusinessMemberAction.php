<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Domain\Subscriptions\SubscriptionFeature;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Services\Subscriptions\BusinessUsageService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddBusinessMemberAction
{
    public function __construct(
        private readonly BusinessUsageService $usage,
        private readonly RecordBusinessStaffAuditAction $staffAudit,
        private readonly NotificationService $notifications,
    ) {}

    public function execute(
        Business $business,
        User $user,
        BusinessMemberRole $role,
        ?string $jobTitle = null,
        ?User $invitedBy = null,
        bool $notify = true,
        ?Request $request = null,
    ): BusinessMember {
        if ($role !== BusinessMemberRole::Owner) {
            $this->usage->assertCanConsume($business, SubscriptionFeature::STAFF_MAX);
        }

        $existing = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing !== null) {
            if ($existing->status === BusinessMemberStatus::Active) {
                throw ValidationException::withMessages([
                    'phone' => [__('business.member_already_exists')],
                ]);
            }

            $existing->update([
                'member_role' => $role,
                'status' => BusinessMemberStatus::Active,
                'job_title' => $jobTitle ?? $existing->job_title,
                'invited_by_user_id' => $invitedBy?->id ?? $existing->invited_by_user_id,
                'joined_at' => $existing->joined_at ?? now(),
            ]);

            $member = $existing->fresh();
        } else {
            try {
                $member = DB::transaction(fn () => BusinessMember::query()->create([
                    'business_id' => $business->id,
                    'user_id' => $user->id,
                    'member_role' => $role,
                    'status' => BusinessMemberStatus::Active,
                    'job_title' => $jobTitle,
                    'invited_by_user_id' => $invitedBy?->id,
                    'joined_at' => now(),
                ]));
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) === '23505') {
                    throw ValidationException::withMessages([
                        'phone' => [__('business.member_already_exists')],
                    ]);
                }

                throw $exception;
            }
        }

        $this->staffAudit->execute(
            action: 'business_staff.added',
            business: $business,
            actor: $invitedBy,
            member: $member,
            newValues: [
                'user_id' => $user->id,
                'member_role' => $role->value,
                'job_title' => $jobTitle,
            ],
            request: $request,
        );

        if ($notify) {
            $this->staffAudit->notifyStaffAdded($user, $business, $role, $this->notifications);
        }

        return $member;
    }
}
