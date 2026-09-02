<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Businesses\AddBusinessMemberAction;
use App\Actions\Businesses\RemoveBusinessMemberAction;
use App\Actions\Businesses\UpdateBusinessMemberAction;
use App\Domain\Businesses\Enums\BusinessMemberRole;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Business\StoreBusinessMemberRequest;
use App\Http\Requests\Api\V1\Business\UpdateBusinessMemberRequest;
use App\Http\Resources\Api\V1\BusinessMemberResource;
use App\Models\Business;
use App\Models\BusinessMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BusinessMemberController extends BaseApiController
{
    public function index(Business $business): JsonResponse
    {
        Gate::authorize('viewMembers', $business);

        $members = BusinessMember::query()
            ->where('business_id', $business->id)
            ->where('status', BusinessMemberStatus::Active)
            ->with(['user:id,name,phone', 'invitedBy:id,name'])
            ->orderBy('member_role')
            ->orderBy('created_at')
            ->get();

        return $this->success(BusinessMemberResource::collection($members));
    }

    public function show(Business $business, BusinessMember $member): JsonResponse
    {
        $this->ensureMemberBelongsToBusiness($business, $member);
        Gate::authorize('viewMembers', $business);

        $member->load(['user:id,name,phone', 'invitedBy:id,name']);

        return $this->success(new BusinessMemberResource($member));
    }

    public function store(
        StoreBusinessMemberRequest $request,
        Business $business,
        AddBusinessMemberAction $addBusinessMember,
    ): JsonResponse {
        $validated = $request->validated();
        $user = User::query()->where('phone', $validated['phone'])->firstOrFail();
        $role = BusinessMemberRole::from($validated['member_role']);

        $member = $addBusinessMember->execute(
            business: $business,
            user: $user,
            role: $role,
            jobTitle: $validated['job_title'] ?? null,
            invitedBy: $request->user(),
            request: $request,
        );
        $member->load(['user:id,name,phone', 'invitedBy:id,name']);

        return $this->created(
            new BusinessMemberResource($member),
            __('business.member_added'),
        );
    }

    public function update(
        UpdateBusinessMemberRequest $request,
        Business $business,
        BusinessMember $member,
        UpdateBusinessMemberAction $updateBusinessMember,
    ): JsonResponse {
        $this->ensureMemberBelongsToBusiness($business, $member);

        $updated = $updateBusinessMember->execute(
            business: $business,
            member: $member,
            attributes: $request->validated(),
            request: $request,
        );
        $updated->load(['user:id,name,phone', 'invitedBy:id,name']);

        return $this->success(
            new BusinessMemberResource($updated),
            __('business.member_updated'),
        );
    }

    public function destroy(
        Business $business,
        BusinessMember $member,
        RemoveBusinessMemberAction $removeBusinessMember,
    ): JsonResponse {
        $this->ensureMemberBelongsToBusiness($business, $member);

        Gate::authorize('removeMember', [$business, $member]);

        $removeBusinessMember->execute($business, $member, request());

        return $this->success(null, __('business.member_removed'));
    }

    private function ensureMemberBelongsToBusiness(Business $business, BusinessMember $member): void
    {
        if ($member->business_id !== $business->id) {
            abort(404);
        }
    }
}
