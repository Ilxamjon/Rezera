# Loyalty, Rewards & Referral Engine

## Overview

Rezera's loyalty and referral module provides:

- **Business-level loyalty programs** — configurable earning rules per business
- **Loyalty accounts & transaction ledger** — auditable integer points
- **Reward catalog & redemption** — business-defined rewards
- **Platform referral system** — invite codes, qualification, platform-scoped referral points

This module integrates with (but does not duplicate):

- Reservation lifecycle (`ReservationStatusChanged` → `completed`)
- Payment refunds (`PaymentRefunded`)
- Notifications (`NotificationService`)
- Audit logs (`CreateAuditLogAction`)

## Architecture

```
Reservation completed
    → LoyaltyService::earnFromReservation()
    → ReferralService::qualifyFromReservation()

Payment refunded
    → LoyaltyService::reverseFromRefund()

Reward redeem API
    → LoyaltyService::redeemReward()
```

### Separation of concerns

| System | Purpose |
|--------|---------|
| Promo codes | Reservation discounts via `DiscountEngine` |
| Loyalty points | Earned from completed reservations |
| Loyalty rewards | Redeemed using points; may grant bonus points or codes |
| Referrals | Platform-scoped invite rewards (separate `business_id = null` accounts) |

## Loyalty Program

Each business may have one `loyalty_programs` record.

| Field | Description |
|-------|-------------|
| `earn_rate_points` | Points earned per `earn_amount` |
| `earn_amount` | Qualifying currency amount in minor units (UZS) |
| `flat_points_per_reservation` | Optional flat bonus per completed reservation |
| `minimum_qualifying_amount` | Minimum `total_amount` to earn |
| `max_points_per_transaction` | Cap per reservation |
| `points_expiration_days` | Optional expiration for earned points |

## Earning Rules

**When:** Points are awarded when a reservation transitions to `completed`.

**Amount basis:** `reservation.total_amount` (final amount after discounts, integer UZS).

**Rounding:** `floor(total_amount / earn_amount) * earn_rate_points` plus any flat bonus.

**Idempotency:** Unique constraint on `(loyalty_account_id, source_type, source_id, type)` where `source_type = reservation`.

## Ledger

`loyalty_transactions` is the authoritative audit trail. `loyalty_accounts.balance` is a cached aggregate updated atomically with each transaction.

Transaction types: `earned`, `redeemed`, `expired`, `adjusted`, `reversed`, `referral`, `bonus`.

Corrections use compensating transactions — historical rows are never mutated.

## Expiration

When `points_expiration_days` is set, earned transactions receive `expires_at`. Run:

```bash
php artisan loyalty:expire-points
```

Expiration creates `expired` ledger entries and is idempotent per source earned transaction.

## Refunds

On `PaymentRefunded`, points are reversed proportionally based on refund amount vs original qualifying amount.

## Reward Redemption

1. Lock reward and account rows
2. Validate availability, stock, per-user limits, balance
3. Create redemption (reserved → redeemed)
4. Debit points via ledger
5. Emit `LoyaltyRewardRedeemed`

Redemption codes use format `RZ-XXXXXX` (cryptographically random).

Discount-type rewards store metadata for future integration with `DiscountEngine`. They do not modify reservation totals directly.

## Referral Flow

1. Each user receives a unique `referral_codes` entry
2. Optional `referral_code` on registration creates a `referrals` row (`registered`)
3. When referred user completes their **first** reservation → `qualified` → `rewarded`
4. Platform referral points credit `loyalty_accounts` with `business_id = null`

Configuration (`config/rezera.php`):

```php
'referral' => [
    'referrer_reward_points' => 100,
    'referred_reward_points' => 50,
],
```

## Security

- No client-controlled points, amounts, or referrer IDs
- Business-scoped loyalty data via policies
- Self-referral prevented
- One referrer per referred user (unique `referred_user_id`)
- Row locks and unique constraints for concurrency

## API Endpoints

### Customer

| Method | Path |
|--------|------|
| GET | `/api/v1/me/loyalty?business_id=` |
| GET | `/api/v1/me/loyalty/transactions` |
| GET | `/api/v1/me/loyalty/redemptions` |
| GET | `/api/v1/businesses/{business}/rewards` |
| POST | `/api/v1/businesses/{business}/rewards/{reward}/redeem` |
| GET | `/api/v1/me/referral` |
| GET | `/api/v1/me/referrals` |
| POST | `/api/v1/referrals/validate` |

### Business manage

| Method | Path |
|--------|------|
| GET/PUT | `/api/v1/manage/businesses/{business}/loyalty` |
| CRUD | `/api/v1/manage/businesses/{business}/rewards` |
| GET | `/api/v1/manage/businesses/{business}/customers/{user}/loyalty` |
| POST | `/api/v1/manage/businesses/{business}/customers/{user}/loyalty/adjust` |

### Admin

| Method | Path |
|--------|------|
| GET | `/api/v1/admin/referrals` |
| GET | `/api/v1/admin/referrals/{referral}` |

## Permissions

- **Owner/Manager:** program and reward management, customer loyalty view, point adjustments
- **Staff:** no loyalty management
- **Customer:** own loyalty data, redeem rewards
- **Platform admin:** referral inspection

## Future Extensions

- Tiered loyalty levels
- Discount reward integration with `PricingEngine`
- Localized reward content
- Advanced fraud detection
- Push notifications for expiring points
