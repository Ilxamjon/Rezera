<?php

namespace App\Actions\Auth;

use App\Domain\Identity\Enums\Locale;
use App\Domain\Identity\Enums\PlatformRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Exceptions\InvalidCredentialsException;
use App\Models\PhoneOtp;
use App\Models\User;
use App\Support\Phone\PhoneNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerifyPhoneOtpAction
{
    /**
     * @return array{user: User, token: string, created: bool}
     */
    public function execute(
        string $phone,
        string $code,
        string $deviceName = 'mobile',
        ?string $name = null,
        string $purpose = 'login',
    ): array {
        $normalized = PhoneNormalizer::normalize($phone);
        $maxAttempts = (int) config('rezera.otp.max_attempts', 5);

        $otp = PhoneOtp::query()
            ->where('phone', $normalized)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->orderByDesc('created_at')
            ->first();

        if ($otp === null || $otp->isExpired()) {
            throw ValidationException::withMessages([
                'code' => [__('auth.otp_invalid')],
            ]);
        }

        if ($otp->attempts >= $maxAttempts) {
            throw ValidationException::withMessages([
                'code' => [__('auth.otp_too_many_attempts')],
            ]);
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');
            throw ValidationException::withMessages([
                'code' => [__('auth.otp_invalid')],
            ]);
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        $user = User::query()->where('phone', $normalized)->first();
        $created = false;

        if ($user === null) {
            $displayName = filled($name) ? trim((string) $name) : 'User '.substr($normalized, -4);
            $user = User::query()->create([
                'name' => $displayName,
                'phone' => $normalized,
                'password' => Str::password(32),
                'locale' => Locale::Ru,
                'platform_role' => PlatformRole::User,
                'status' => UserStatus::Active,
                'phone_verified_at' => now(),
            ]);
            $created = true;
        } else {
            if ($user->status !== UserStatus::Active) {
                throw new InvalidCredentialsException;
            }
            $user->forceFill([
                'phone_verified_at' => $user->phone_verified_at ?? now(),
                'last_login_at' => now(),
            ])->save();
        }

        if (! $created) {
            $user->forceFill(['last_login_at' => now()])->save();
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user->fresh(),
            'token' => $token,
            'created' => $created,
        ];
    }
}
