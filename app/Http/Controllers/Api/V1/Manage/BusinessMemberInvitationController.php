<?php

namespace App\Http\Controllers\Api\V1\Manage;

use App\Actions\Businesses\InviteBusinessMemberAction;
use App\Actions\Businesses\RevokeBusinessMemberInvitationAction;
use App\Domain\Businesses\Enums\BusinessMemberInvitationStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Business\InviteBusinessMemberRequest;
use App\Http\Resources\Api\V1\BusinessMemberInvitationResource;
use App\Models\Business;
use App\Models\BusinessMemberInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BusinessMemberInvitationController extends BaseApiController
{
    public function index(Business $business): JsonResponse
    {
        Gate::authorize('viewMembers', $business);

        $invitations = BusinessMemberInvitation::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [
                BusinessMemberInvitationStatus::Pending,
                BusinessMemberInvitationStatus::Accepted,
                BusinessMemberInvitationStatus::Declined,
                BusinessMemberInvitationStatus::Revoked,
            ])
            ->with(['invitedBy:id,name', 'invitedUser:id,name,phone'])
            ->orderByDesc('created_at')
            ->get();

        return $this->success(BusinessMemberInvitationResource::collection($invitations));
    }

    public function store(
        InviteBusinessMemberRequest $request,
        Business $business,
        InviteBusinessMemberAction $inviteMember,
    ): JsonResponse {
        $invitation = $inviteMember->execute(
            business: $business,
            inviter: $request->user(),
            data: $request->validated(),
            request: $request,
        );

        return $this->created(
            new BusinessMemberInvitationResource($invitation),
            __('business.invitation_sent'),
        );
    }

    public function destroy(
        Business $business,
        BusinessMemberInvitation $invitation,
        RevokeBusinessMemberInvitationAction $revokeInvitation,
    ): JsonResponse {
        Gate::authorize('manageMembers', $business);
        $this->ensureInvitationBelongsToBusiness($business, $invitation);

        $revoked = $revokeInvitation->execute(
            business: $business,
            invitation: $invitation,
            actor: request()->user(),
            request: request(),
        );

        return $this->success(
            new BusinessMemberInvitationResource($revoked),
            __('business.invitation_revoked'),
        );
    }

    private function ensureInvitationBelongsToBusiness(Business $business, BusinessMemberInvitation $invitation): void
    {
        if ($invitation->business_id !== $business->id) {
            abort(404);
        }
    }
}
