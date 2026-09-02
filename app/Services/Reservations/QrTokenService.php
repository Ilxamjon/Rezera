<?php

namespace App\Services\Reservations;

use App\Models\Resource;
use App\Models\ResourceQrToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class QrTokenService
{
    public function generate(Resource $resource, ?string $createdByUserId = null): ResourceQrToken
    {
        return DB::transaction(function () use ($resource, $createdByUserId): ResourceQrToken {
            ResourceQrToken::query()
                ->where('resource_id', $resource->id)
                ->where('is_active', true)
                ->whereNull('revoked_at')
                ->update([
                    'is_active' => false,
                    'revoked_at' => now(),
                ]);

            return ResourceQrToken::query()->create([
                'business_id' => $resource->business_id,
                'resource_id' => $resource->id,
                'token' => $this->generateToken(),
                'is_active' => true,
                'created_by_user_id' => $createdByUserId,
            ]);
        });
    }

    public function revoke(Resource $resource): void
    {
        ResourceQrToken::query()
            ->where('resource_id', $resource->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'revoked_at' => now(),
            ]);
    }

    public function activeToken(Resource $resource): ?ResourceQrToken
    {
        return ResourceQrToken::query()
            ->where('resource_id', $resource->id)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->first();
    }

    public function validateForReservation(string $token, string $expectedResourceId, string $expectedBusinessId): ResourceQrToken
    {
        $qrToken = ResourceQrToken::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->first();

        if ($qrToken === null) {
            throw ValidationException::withMessages([
                'qr_token' => [__('reservations.check_in.invalid_qr_token')],
            ]);
        }

        if ($qrToken->business_id !== $expectedBusinessId || $qrToken->resource_id !== $expectedResourceId) {
            throw ValidationException::withMessages([
                'qr_token' => [__('reservations.check_in.wrong_resource_qr')],
            ]);
        }

        $qrToken->update(['last_used_at' => now()]);

        return $qrToken;
    }

    private function generateToken(): string
    {
        do {
            $token = Str::upper(Str::random(48));
        } while (ResourceQrToken::query()->where('token', $token)->exists());

        return $token;
    }
}
