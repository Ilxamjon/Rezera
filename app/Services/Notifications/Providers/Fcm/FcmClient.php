<?php

namespace App\Services\Notifications\Providers\Fcm;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FcmClient
{
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function sendToToken(string $deviceToken, string $title, string $body, array $data = []): FcmSendResult
    {
        $account = $this->serviceAccount();
        $projectId = $this->projectId($account);

        if ($account === null || $projectId === null) {
            return new FcmSendResult(false, errorMessage: 'FCM credentials are not configured');
        }

        try {
            $accessToken = $this->accessToken($account);
        } catch (\Throwable $exception) {
            return new FcmSendResult(false, errorMessage: $exception->getMessage());
        }

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $this->stringifyData($data),
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::timeout(10)
            ->acceptJson()
            ->withToken($accessToken)
            ->post(
                'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send',
                $payload,
            );

        if ($response->successful()) {
            $name = $response->json('name');

            return new FcmSendResult(
                true,
                messageName: is_string($name) ? $name : null,
            );
        }

        return new FcmSendResult(
            false,
            errorMessage: $this->errorMessage($response),
            unregistered: $this->isUnregistered($response),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function stringifyData(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (is_bool($value)) {
                $out[$key] = $value ? 'true' : 'false';

                continue;
            }

            if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
                $out[$key] = (string) $value;

                continue;
            }

            $encoded = json_encode($value);
            $out[$key] = is_string($encoded) ? $encoded : '';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $account
     */
    private function projectId(?array $account): ?string
    {
        $configured = config('notifications.fcm.project_id');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $fromAccount = $account['project_id'] ?? null;

        return is_string($fromAccount) && $fromAccount !== '' ? $fromAccount : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serviceAccount(): ?array
    {
        $inline = config('notifications.fcm.credentials_json');
        if (is_string($inline) && $inline !== '') {
            $decoded = json_decode($inline, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $path = config('notifications.fcm.credentials_path');
        if (! is_string($path) || $path === '') {
            $envPath = getenv('GOOGLE_APPLICATION_CREDENTIALS');
            $path = is_string($envPath) ? $envPath : null;
        }

        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function accessToken(array $account): string
    {
        $override = config('notifications.fcm.access_token');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        $cacheKey = 'notifications.fcm.access_token.'.hash('sha256', (string) ($account['client_email'] ?? 'default'));

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $jwt = $this->assertion($account);

        $response = Http::asForm()
            ->timeout(10)
            ->post(self::OAUTH_TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('FCM OAuth token request failed');
        }

        $token = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('FCM OAuth token missing from response');
        }

        Cache::put($cacheKey, $token, max(60, $expiresIn - 60));

        return $token;
    }

    /**
     * @param  array<string, mixed>  $account
     */
    private function assertion(array $account): string
    {
        $email = $account['client_email'] ?? null;
        $privateKey = $account['private_key'] ?? null;

        if (! is_string($email) || $email === '' || ! is_string($privateKey) || $privateKey === '') {
            throw new RuntimeException('FCM service account is incomplete');
        }

        $now = time();
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $email,
            'sub' => $email,
            'aud' => self::OAUTH_TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => self::MESSAGING_SCOPE,
        ], JSON_THROW_ON_ERROR));

        $unsigned = $header.'.'.$payload;
        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $ok) {
            throw new RuntimeException('Unable to sign FCM service-account JWT');
        }

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function isUnregistered(Response $response): bool
    {
        $status = $response->json('error.status');
        if ($status === 'NOT_FOUND') {
            return true;
        }

        $details = $response->json('error.details');
        if (! is_array($details)) {
            return false;
        }

        foreach ($details as $detail) {
            if (is_array($detail) && ($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        return false;
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return 'FCM request failed with HTTP '.$response->status();
    }
}
