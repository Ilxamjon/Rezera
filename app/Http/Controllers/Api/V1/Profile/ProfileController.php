<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Actions\Profile\UpdateUserProfileAction;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends BaseApiController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success(new UserProfileResource($user));
    }

    public function update(UpdateProfileRequest $request, UpdateUserProfileAction $updateProfile): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $updateProfile->execute($user, $request->validated());

        return $this->success(
            new UserProfileResource($updated),
            __('auth.profile_updated'),
        );
    }
}
