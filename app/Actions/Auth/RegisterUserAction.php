<?php

namespace App\Actions\Auth;

use App\Domain\Identity\Enums\Locale;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use App\Services\Referrals\ReferralService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterUserAction
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {}

    /**
     * @param  array{name: string, phone: string, password: string, email?: string|null, locale?: string|null, referral_code?: string|null}  $data
     * @return array{user: User, token: string}
     */
    public function execute(array $data, string $deviceName = 'mobile'): array
    {
        try {
            $user = DB::transaction(function () use ($data): User {
                return User::query()->create([
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'password' => $data['password'],
                    'locale' => isset($data['locale'])
                        ? Locale::from($data['locale'])
                        : Locale::default(),
                    'platform_role' => PlatformRole::User,
                    'status' => UserStatus::Active,
                ]);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                throw ValidationException::withMessages([
                    'phone' => [__('validation.unique', ['attribute' => 'phone'])],
                ]);
            }

            throw $exception;
        }

        if (! empty($data['referral_code'])) {
            $this->referralService->registerReferral($user, $data['referral_code']);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
