<?php

namespace App\Actions\Auth;

use App\Contracts\Notifications\SmsProviderInterface;
use App\Models\PhoneOtp;
use App\Services\Notifications\NotificationChannelResolver;
use App\Support\Phone\PhoneNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RequestPhoneOtpAction
{
    public function __construct(
        private readonly NotificationChannelResolver $channels,
    ) {}

    /**
     * @return array{expires_in: int, debug_code?: string}
     */
    public function execute(string $phone, string $purpose = 'login', ?string $ip = null): array
    {
        // No real SMS driver is implemented yet. A mock must never claim to send login codes.
        if (app()->environment('production')) {
            throw ValidationException::withMessages([
                'phone' => [__('auth.otp_send_failed')],
            ]);
        }

        $normalized = PhoneNormalizer::normalize($phone);
        $ttl = (int) config('rezera.otp.ttl_seconds', 300);
        $code = (string) random_int(100000, 999999);

        PhoneOtp::query()
            ->where('phone', $normalized)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        PhoneOtp::query()->create([
            'phone' => $normalized,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addSeconds($ttl),
            'request_ip' => $ip,
        ]);

        /** @var SmsProviderInterface $sms */
        $sms = $this->channels->smsProvider();
        $message = __('auth.otp_sms_message', ['code' => $code]);
        $result = $sms->sendSms($normalized, $message);

        if (! $result->success) {
            throw ValidationException::withMessages([
                'phone' => [__('auth.otp_send_failed')],
            ]);
        }

        $payload = ['expires_in' => $ttl];

        if (config('rezera.otp.expose_debug_code', false) || app()->environment('local', 'testing')) {
            $payload['debug_code'] = $code;
        }

        return $payload;
    }
}
