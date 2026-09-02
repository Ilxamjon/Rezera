<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Businesses\AcceptBusinessMemberInvitationAction;
use App\Actions\Businesses\DeclineBusinessMemberInvitationAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Resources\Api\V1\BusinessMemberInvitationResource;
use App\Http\Resources\Api\V1\BusinessMemberResource;
use App\Models\BusinessMemberInvitation;
use App\Services\Businesses\BusinessStaffInvitationService;
use Illuminate\Http\JsonResponse;

class BusinessInvitationController extends BaseApiController
{
    public function index(BusinessStaffInvitationService $invitations): JsonResponse
    {
        $items = $invitations->pendingForUser(request()->user());

        return $this->success(BusinessMemberInvitationResource::collection(collect($items)));
    }

    public function accept(
        BusinessMemberInvitation $invitation,
        AcceptBusinessMemberInvitationAction $acceptInvitation,
    ): JsonResponse {
        $this->authorizeInvitation($invitation);

        $member = $acceptInvitation->execute($invitation, request()->user(), request());
        $member->load('user:id,name,phone');

        return $this->success(
            new BusinessMemberResource($member),
            __('business.invitation_accepted'),
        );
    }

    public function decline(
        BusinessMemberInvitation $invitation,
        DeclineBusinessMemberInvitationAction $declineInvitation,
    ): JsonResponse {
        $this->authorizeInvitation($invitation);

        $declined = $declineInvitation->execute($invitation, request()->user(), request());

        return $this->success(
            new BusinessMemberInvitationResource($declined),
            __('business.invitation_declined'),
        );
    }

    private function authorizeInvitation(BusinessMemberInvitation $invitation): void
    {
        $user = request()->user();

        if ($invitation->phone !== $user?->phone) {
            abort(404);
        }
    }
}
