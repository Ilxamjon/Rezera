<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\LogoutUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\Actions\Auth\RequestPhoneOtpAction;
use App\Actions\Auth\VerifyPhoneOtpAction;
use App\Domain\Businesses\Enums\BusinessMemberStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\RequestOtpRequest;
use App\Http\Requests\Api\V1\Auth\VerifyOtpRequest;
use App\Http\Resources\Api\V1\AuthTokenResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseApiController
{
    public function register(RegisterRequest $request, RegisterUserAction $registerUser): JsonResponse
    {
        $validated = $request->validated();

        $result = $registerUser->execute(
            data: [
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'email' => $validated['email'] ?? null,
                'locale' => $validated['locale'] ?? null,
                'referral_code' => $validated['referral_code'] ?? null,
            ],
            deviceName: $validated['device_name'] ?? 'mobile',
        );

        return $this->created(
            new AuthTokenResource($result),
            __('auth.registration_successful'),
        );
    }

    public function login(LoginRequest $request, AuthenticateUserAction $authenticateUser): JsonResponse
    {
        $validated = $request->validated();

        $result = $authenticateUser->execute(
            phone: $validated['phone'],
            password: $validated['password'],
            deviceName: $validated['device_name'] ?? 'mobile',
        );

        return $this->success(
            new AuthTokenResource($result),
            __('auth.login_successful'),
        );
    }

    public function requestOtp(RequestOtpRequest $request, RequestPhoneOtpAction $requestOtp): JsonResponse
    {
        $validated = $request->validated();

        $result = $requestOtp->execute(
            phone: $validated['phone'],
            purpose: $validated['purpose'] ?? 'login',
            ip: $request->ip(),
        );

        return $this->success($result, __('auth.otp_sent'));
    }

    public function verifyOtp(VerifyOtpRequest $request, VerifyPhoneOtpAction $verifyOtp): JsonResponse
    {
        $validated = $request->validated();

        $result = $verifyOtp->execute(
            phone: $validated['phone'],
            code: $validated['code'],
            deviceName: $validated['device_name'] ?? 'mobile',
            name: $validated['name'] ?? null,
            purpose: $validated['purpose'] ?? 'login',
        );

        return $this->success(
            new AuthTokenResource($result),
            $result['created'] ? __('auth.registration_successful') : __('auth.login_successful'),
        );
    }

    public function logout(Request $request, LogoutUserAction $logoutUser): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $logoutUser->execute($user, $request->bearerToken());

        return $this->success(null, __('auth.logout_successful'));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->load([
            'businessMemberships.business' => fn ($query) => $query->select('id', 'name'),
        ]);
        $user->setRelation(
            'businessMemberships',
            $user->businessMemberships->where('status', BusinessMemberStatus::Active)->values()
        );

        return $this->success(new UserResource($user));
    }
}
