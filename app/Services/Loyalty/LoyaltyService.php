<?php

namespace App\Services\Loyalty;

use App\Domain\Loyalty\Enums\LoyaltyAccountStatus;
use App\Domain\Loyalty\Enums\LoyaltyRedemptionStatus;
use App\Domain\Loyalty\Enums\LoyaltyRewardType;
use App\Domain\Loyalty\Enums\LoyaltyTransactionType;
use App\Events\Loyalty\LoyaltyPointsEarned;
use App\Events\Loyalty\LoyaltyRewardRedeemed;
use App\Models\Business;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LoyaltyService
{
    public const SOURCE_RESERVATION = 'reservation';

    public const SOURCE_PAYMENT_REFUND = 'payment_refund';

    public const SOURCE_REDEMPTION = 'loyalty_redemption';

    public const SOURCE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const SOURCE_REFERRAL = 'referral';

    public const SOURCE_REFERRAL_REFERRED = 'referral_referred';

    public function __construct(
        private readonly LoyaltyPointsCalculator $calculator,
        private readonly LoyaltyCodeGenerator $codeGenerator,
    ) {}

    public function getProgram(Business $business): ?LoyaltyProgram
    {
        return LoyaltyProgram::query()->where('business_id', $business->id)->first();
    }

    public function getOrCreateAccount(?string $businessId, User $user): LoyaltyAccount
    {
        return LoyaltyAccount::query()->firstOrCreate(
            [
                'business_id' => $businessId,
                'user_id' => $user->id,
            ],
            [
                'balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_redeemed' => 0,
                'status' => LoyaltyAccountStatus::Active,
            ],
        );
    }

    public function earnFromReservation(Reservation $reservation): ?LoyaltyTransaction
    {
        $program = $this->getProgram($reservation->business);

        if ($program === null || ! $program->is_enabled) {
            return null;
        }

        if ($reservation->customer_id === null) {
            return null;
        }

        $customer = $reservation->customer ?? User::query()->find($reservation->customer_id);

        if ($customer === null) {
            return null;
        }

        $qualifyingAmount = (int) $reservation->total_amount;
        $points = $this->calculator->calculate($program, $qualifyingAmount);

        if ($points <= 0) {
            return null;
        }

        return $this->creditPoints(
            businessId: $reservation->business_id,
            user: $customer,
            points: $points,
            type: LoyaltyTransactionType::Earned,
            sourceType: self::SOURCE_RESERVATION,
            sourceId: $reservation->id,
            description: __('loyalty.transactions.earned_from_reservation', [
                'number' => $reservation->reservation_number,
            ]),
            expirationDays: $program->points_expiration_days,
            metadata: [
                'reservation_number' => $reservation->reservation_number,
                'qualifying_amount' => $qualifyingAmount,
            ],
            onCreated: static function (LoyaltyTransaction $transaction) use ($reservation): void {
                LoyaltyPointsEarned::dispatch($transaction, $reservation);
            },
        );
    }

    public function reverseFromRefund(Payment $payment): ?LoyaltyTransaction
    {
        $reservation = $payment->reservation;

        if ($reservation === null || $reservation->customer_id === null) {
            return null;
        }

        $customer = $reservation->customer ?? User::query()->find($reservation->customer_id);

        if ($customer === null) {
            return null;
        }

        $earned = LoyaltyTransaction::query()
            ->where('source_type', self::SOURCE_RESERVATION)
            ->where('source_id', $reservation->id)
            ->where('type', LoyaltyTransactionType::Earned)
            ->first();

        if ($earned === null) {
            return null;
        }

        $originalAmount = (int) ($earned->metadata['qualifying_amount'] ?? $reservation->total_amount);
        $reversalPoints = $this->calculator->proportionalReversal(
            $earned->points,
            $originalAmount,
            (int) $payment->amount,
        );

        if ($reversalPoints <= 0) {
            return null;
        }

        return $this->debitPoints(
            businessId: $reservation->business_id,
            user: $customer,
            points: $reversalPoints,
            type: LoyaltyTransactionType::Reversed,
            sourceType: self::SOURCE_PAYMENT_REFUND,
            sourceId: $payment->id,
            description: __('loyalty.transactions.reversed_from_refund', [
                'number' => $reservation->reservation_number,
            ]),
            metadata: [
                'payment_id' => $payment->id,
                'reservation_id' => $reservation->id,
                'refunded_amount' => $payment->amount,
            ],
        );
    }

    public function redeemReward(User $user, Business $business, LoyaltyReward $reward): LoyaltyRedemption
    {
        if ($reward->business_id !== $business->id) {
            throw ValidationException::withMessages([
                'reward' => [__('loyalty.reward_not_found')],
            ]);
        }

        if (! $reward->isAvailable()) {
            throw ValidationException::withMessages([
                'reward' => [__('loyalty.reward_unavailable')],
            ]);
        }

        $program = $this->getProgram($business);

        if ($program === null || ! $program->is_enabled) {
            throw ValidationException::withMessages([
                'loyalty' => [__('loyalty.program_disabled')],
            ]);
        }

        return DB::transaction(function () use ($user, $business, $reward, $program): LoyaltyRedemption {
            $lockedReward = LoyaltyReward::query()->whereKey($reward->id)->lockForUpdate()->firstOrFail();

            if (! $lockedReward->isAvailable()) {
                throw ValidationException::withMessages([
                    'reward' => [__('loyalty.reward_unavailable')],
                ]);
            }

            if ($lockedReward->max_redemptions_per_user !== null) {
                $userCount = LoyaltyRedemption::query()
                    ->where('loyalty_reward_id', $lockedReward->id)
                    ->where('user_id', $user->id)
                    ->whereNotIn('status', [LoyaltyRedemptionStatus::Cancelled])
                    ->count();

                if ($userCount >= $lockedReward->max_redemptions_per_user) {
                    throw ValidationException::withMessages([
                        'reward' => [__('loyalty.reward_limit_reached')],
                    ]);
                }
            }

            $account = LoyaltyAccount::query()
                ->where('business_id', $business->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($account === null || $account->status !== LoyaltyAccountStatus::Active) {
                throw ValidationException::withMessages([
                    'balance' => [__('loyalty.insufficient_balance')],
                ]);
            }

            if ($account->balance < $lockedReward->points_cost) {
                throw ValidationException::withMessages([
                    'balance' => [__('loyalty.insufficient_balance')],
                ]);
            }

            $redemption = LoyaltyRedemption::query()->create([
                'business_id' => $business->id,
                'user_id' => $user->id,
                'loyalty_reward_id' => $lockedReward->id,
                'loyalty_account_id' => $account->id,
                'points_spent' => $lockedReward->points_cost,
                'status' => LoyaltyRedemptionStatus::Reserved,
                'redemption_code' => $this->codeGenerator->redemptionCode(),
                'redeemed_at' => null,
                'expires_at' => now()->addDays(30),
                'metadata' => [
                    'reward_type' => $lockedReward->reward_type->value,
                    'reward_value' => $lockedReward->value,
                ],
            ]);

            $this->debitPoints(
                businessId: $business->id,
                user: $user,
                points: $lockedReward->points_cost,
                type: LoyaltyTransactionType::Redeemed,
                sourceType: self::SOURCE_REDEMPTION,
                sourceId: $redemption->id,
                description: __('loyalty.transactions.redeemed_reward', [
                    'name' => $lockedReward->name,
                ]),
                account: $account,
            );

            if ($lockedReward->stock !== null) {
                $lockedReward->decrement('stock');
            }

            $redemption->update([
                'status' => LoyaltyRedemptionStatus::Redeemed,
                'redeemed_at' => now(),
            ]);

            if ($lockedReward->reward_type === LoyaltyRewardType::BonusPoints && $lockedReward->value > 0) {
                $this->creditPoints(
                    businessId: $business->id,
                    user: $user,
                    points: (int) $lockedReward->value,
                    type: LoyaltyTransactionType::Bonus,
                    sourceType: self::SOURCE_REDEMPTION,
                    sourceId: $redemption->id,
                    description: __('loyalty.transactions.bonus_from_reward', [
                        'name' => $lockedReward->name,
                    ]),
                    expirationDays: $program->points_expiration_days,
                );
            }

            LoyaltyRewardRedeemed::dispatch($redemption->fresh());

            return $redemption->fresh()->load(['reward', 'business']);
        });
    }

    public function adjustPoints(
        Business $business,
        User $customer,
        int $points,
        string $reason,
        ?User $actor = null,
    ): LoyaltyTransaction {
        if ($points === 0) {
            throw ValidationException::withMessages([
                'points' => [__('loyalty.adjustment_zero')],
            ]);
        }

        if ($points > 0) {
            return $this->creditPoints(
                businessId: $business->id,
                user: $customer,
                points: $points,
                type: LoyaltyTransactionType::Adjusted,
                sourceType: self::SOURCE_MANUAL_ADJUSTMENT,
                sourceId: $actor?->id,
                description: $reason,
                metadata: [
                    'reason' => $reason,
                    'actor_user_id' => $actor?->id,
                ],
            );
        }

        return $this->debitPoints(
            businessId: $business->id,
            user: $customer,
            points: abs($points),
            type: LoyaltyTransactionType::Adjusted,
            sourceType: self::SOURCE_MANUAL_ADJUSTMENT,
            sourceId: $actor?->id,
            description: $reason,
            metadata: [
                'reason' => $reason,
                'actor_user_id' => $actor?->id,
            ],
        );
    }

    public function awardReferralPoints(
        User $user,
        int $points,
        string $referralId,
        string $description,
        string $sourceType = LoyaltyService::SOURCE_REFERRAL,
    ): ?LoyaltyTransaction {
        if ($points <= 0) {
            return null;
        }

        return $this->creditPoints(
            businessId: null,
            user: $user,
            points: $points,
            type: LoyaltyTransactionType::Referral,
            sourceType: $sourceType,
            sourceId: $referralId,
            description: $description,
            expirationDays: null,
            metadata: ['referral_id' => $referralId],
        );
    }

    public function expireDuePoints(): int
    {
        $expiredCount = 0;

        $grouped = LoyaltyTransaction::query()
            ->where('type', LoyaltyTransactionType::Earned)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('points', '>', 0)
            ->get()
            ->groupBy('loyalty_account_id');

        foreach ($grouped as $accountId => $transactions) {
            $account = LoyaltyAccount::query()->find($accountId);

            if ($account === null || $account->status !== LoyaltyAccountStatus::Active) {
                continue;
            }

            foreach ($transactions as $transaction) {
                $alreadyExpired = LoyaltyTransaction::query()
                    ->where('loyalty_account_id', $account->id)
                    ->where('type', LoyaltyTransactionType::Expired)
                    ->where('source_type', 'loyalty_transaction')
                    ->where('source_id', $transaction->id)
                    ->exists();

                if ($alreadyExpired) {
                    continue;
                }

                $pointsToExpire = min($transaction->points, $account->balance);

                if ($pointsToExpire <= 0) {
                    continue;
                }

                $this->debitPoints(
                    businessId: $account->business_id,
                    user: $account->user,
                    points: $pointsToExpire,
                    type: LoyaltyTransactionType::Expired,
                    sourceType: 'loyalty_transaction',
                    sourceId: $transaction->id,
                    description: __('loyalty.transactions.points_expired'),
                    account: $account,
                );

                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    /**
     * @param  callable(LoyaltyTransaction): void|null  $onCreated
     */
    private function creditPoints(
        ?string $businessId,
        User $user,
        int $points,
        LoyaltyTransactionType $type,
        string $sourceType,
        ?string $sourceId,
        string $description,
        ?int $expirationDays = null,
        ?array $metadata = null,
        ?callable $onCreated = null,
    ): ?LoyaltyTransaction {
        if ($points <= 0) {
            return null;
        }

        try {
            return DB::transaction(function () use (
                $businessId, $user, $points, $type, $sourceType, $sourceId,
                $description, $expirationDays, $metadata, $onCreated
            ): LoyaltyTransaction {
                $account = LoyaltyAccount::query()
                    ->where('business_id', $businessId)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($account === null) {
                    $account = $this->getOrCreateAccount($businessId, $user);
                    $account = LoyaltyAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
                }

                if ($account->status !== LoyaltyAccountStatus::Active) {
                    return null;
                }

                $newBalance = $account->balance + $points;

                $transaction = LoyaltyTransaction::query()->create([
                    'loyalty_account_id' => $account->id,
                    'business_id' => $businessId,
                    'user_id' => $user->id,
                    'type' => $type,
                    'points' => $points,
                    'balance_after' => $newBalance,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'description' => $description,
                    'metadata' => $metadata,
                    'expires_at' => $expirationDays !== null ? now()->addDays($expirationDays) : null,
                    'created_at' => now(),
                ]);

                $account->update([
                    'balance' => $newBalance,
                    'lifetime_earned' => $account->lifetime_earned + $points,
                ]);

                if ($onCreated !== null) {
                    $onCreated($transaction);
                }

                return $transaction;
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                return LoyaltyTransaction::query()
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->where('type', $type)
                    ->first();
            }

            throw $exception;
        }
    }

    private function debitPoints(
        ?string $businessId,
        User $user,
        int $points,
        LoyaltyTransactionType $type,
        string $sourceType,
        ?string $sourceId,
        string $description,
        ?array $metadata = null,
        ?LoyaltyAccount $account = null,
    ): ?LoyaltyTransaction {
        if ($points <= 0) {
            return null;
        }

        try {
            return DB::transaction(function () use (
                $businessId, $user, $points, $type, $sourceType, $sourceId,
                $description, $metadata, $account
            ): LoyaltyTransaction {
                $account ??= LoyaltyAccount::query()
                    ->where('business_id', $businessId)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($account->balance < $points) {
                    throw ValidationException::withMessages([
                        'balance' => [__('loyalty.insufficient_balance')],
                    ]);
                }

                $newBalance = $account->balance - $points;

                $transaction = LoyaltyTransaction::query()->create([
                    'loyalty_account_id' => $account->id,
                    'business_id' => $businessId,
                    'user_id' => $user->id,
                    'type' => $type,
                    'points' => -$points,
                    'balance_after' => $newBalance,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'description' => $description,
                    'metadata' => $metadata,
                    'expires_at' => null,
                    'created_at' => now(),
                ]);

                $updates = ['balance' => $newBalance];

                if ($type === LoyaltyTransactionType::Redeemed) {
                    $updates['lifetime_redeemed'] = $account->lifetime_redeemed + $points;
                }

                $account->update($updates);

                return $transaction;
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                return LoyaltyTransaction::query()
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->where('type', $type)
                    ->first();
            }

            throw $exception;
        }
    }
}
