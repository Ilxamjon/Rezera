<?php

namespace App\Services\Referrals;

use App\Domain\Referrals\Enums\ReferralStatus;
use App\Events\Referrals\ReferralQualified;
use App\Events\Referrals\ReferralRegistered;
use App\Events\Referrals\ReferralRewarded;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Referrals\ReferralService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReferralService
{
    public function __construct(
        private readonly ReferralCodeGenerator $codeGenerator,
        private readonly LoyaltyService $loyaltyService,
    ) {}

    public function getOrCreateCode(User $user): ReferralCode
    {
        return ReferralCode::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'code' => $this->codeGenerator->generate(),
                'is_active' => true,
            ],
        );
    }

    public function findActiveCode(string $code): ?ReferralCode
    {
        return ReferralCode::query()
            ->where('code', strtoupper(trim($code)))
            ->where('is_active', true)
            ->first();
    }

    public function registerReferral(User $referredUser, string $code): ?Referral
    {
        $referralCode = $this->findActiveCode($code);

        if ($referralCode === null) {
            throw ValidationException::withMessages([
                'referral_code' => [__('referrals.invalid_code')],
            ]);
        }

        if ($referralCode->user_id === $referredUser->id) {
            throw ValidationException::withMessages([
                'referral_code' => [__('referrals.self_referral')],
            ]);
        }

        try {
            $referral = DB::transaction(function () use ($referralCode, $referredUser): Referral {
                return Referral::query()->create([
                    'referrer_user_id' => $referralCode->user_id,
                    'referred_user_id' => $referredUser->id,
                    'referral_code_id' => $referralCode->id,
                    'status' => ReferralStatus::Registered,
                ]);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                return Referral::query()->where('referred_user_id', $referredUser->id)->first();
            }

            throw $exception;
        }

        ReferralRegistered::dispatch($referral);

        return $referral;
    }

    public function qualifyFromReservation(Reservation $reservation): ?Referral
    {
        if ($reservation->customer_id === null) {
            return null;
        }

        $referral = Referral::query()
            ->where('referred_user_id', $reservation->customer_id)
            ->where('status', ReferralStatus::Registered)
            ->first();

        if ($referral === null) {
            return null;
        }

        $priorCompleted = Reservation::query()
            ->where('customer_id', $reservation->customer_id)
            ->where('status', \App\Domain\Reservations\Enums\ReservationStatus::Completed)
            ->where('id', '!=', $reservation->id)
            ->exists();

        if ($priorCompleted) {
            return null;
        }

        $referral = DB::transaction(function () use ($referral): Referral {
            $locked = Referral::query()->whereKey($referral->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReferralStatus::Registered) {
                return $locked;
            }

            $locked->update([
                'status' => ReferralStatus::Qualified,
                'qualified_at' => now(),
            ]);

            return $locked->fresh();
        });

        if ($referral->status === ReferralStatus::Qualified) {
            ReferralQualified::dispatch($referral, $reservation);
            $this->awardReferralRewards($referral);
        }

        return $referral;
    }

    public function awardReferralRewards(Referral $referral): void
    {
        if ($referral->status !== ReferralStatus::Qualified) {
            return;
        }

        $referrerPoints = (int) config('rezera.referral.referrer_reward_points', 100);
        $referredPoints = (int) config('rezera.referral.referred_reward_points', 50);

        DB::transaction(function () use ($referral, $referrerPoints, $referredPoints): void {
            $locked = Referral::query()->whereKey($referral->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ReferralStatus::Rewarded) {
                return;
            }

            if ($locked->status !== ReferralStatus::Qualified) {
                return;
            }

            $referrer = $locked->referrer;
            $referred = $locked->referred;

            if ($referrerPoints > 0) {
                $this->loyaltyService->awardReferralPoints(
                    $referrer,
                    $referrerPoints,
                    $locked->id,
                    __('referrals.referrer_reward_description'),
                );
            }

            if ($referredPoints > 0) {
                $this->loyaltyService->awardReferralPoints(
                    $referred,
                    $referredPoints,
                    $locked->id,
                    __('referrals.referred_reward_description'),
                    LoyaltyService::SOURCE_REFERRAL_REFERRED,
                );
            }

            $locked->update([
                'status' => ReferralStatus::Rewarded,
                'rewarded_at' => now(),
            ]);

            ReferralRewarded::dispatch($locked->fresh());
        });
    }

    /**
     * @return array{total_referred: int, qualified: int, rewarded: int}
     */
    public function summaryForUser(User $user): array
    {
        $query = Referral::query()->where('referrer_user_id', $user->id);

        return [
            'total_referred' => (clone $query)->count(),
            'qualified' => (clone $query)->whereIn('status', [ReferralStatus::Qualified, ReferralStatus::Rewarded])->count(),
            'rewarded' => (clone $query)->where('status', ReferralStatus::Rewarded)->count(),
        ];
    }
}
