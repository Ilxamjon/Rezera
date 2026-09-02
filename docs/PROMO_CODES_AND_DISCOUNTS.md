# Promo Codes, Coupons & Discounts

This document describes the Rezera promo code and discount foundation introduced in Prompt #17.

## Overview

The system supports:

- Business-specific promo codes (`business_id` set)
- Platform-wide promotions (`business_id` null)
- Percentage and fixed-amount discounts
- Minimum reservation amount rules
- Maximum discount caps for percentage promos
- Validity periods (`starts_at`, `ends_at`)
- Global and per-user usage limits
- Server-side discount calculation only
- One promo per reservation (no stacking)
- Discount snapshots on reservations
- Redemption audit trail

## Data Model

### `promo_codes`

| Field | Description |
|-------|-------------|
| `business_id` | Nullable. `null` = platform promo |
| `code` | Canonical uppercase code (normalized on write) |
| `discount_type` | `percentage` or `fixed` |
| `discount_value` | Integer. Percentage: 1–100. Fixed: amount in minor units (UZS) |
| `currency` | Required for fixed discounts |
| `minimum_amount` | Optional subtotal threshold |
| `maximum_discount` | Optional cap for percentage discounts |
| `usage_limit` / `usage_count` | Global redemption limits |
| `per_user_limit` | Per-user redemption limit |
| `is_active` | Deactivation without deleting history |

**Uniqueness:** `(business_id, code)` for business promos; `(code)` for platform promos (partial unique indexes).

### `promo_code_redemptions`

Tracks each application:

| Field | Description |
|-------|-------------|
| `status` | `reserved`, `redeemed`, `cancelled` |
| `reservation_id` | One redemption per reservation |
| `discount_amount` | Applied discount in minor units |

Usage counters increment only on successful reservation creation (`redeemed`).

### Reservation pricing fields

| Field | Description |
|-------|-------------|
| `subtotal_amount` | Pre-discount total |
| `discount_amount` | Applied discount |
| `total_amount` | `max(subtotal - discount, 0)` |
| `promo_code_snapshot` | Code at booking time |
| `discount_type` / `discount_value_snapshot` | Promo terms at booking time |

Constraint: `total_amount = GREATEST(subtotal_amount - discount_amount, 0)`.

## Promo Code Normalization

Codes are trimmed and uppercased before storage and lookup:

- `game10`, `Game10`, ` GAME10 ` → `GAME10`

Lookup is case-insensitive via canonical storage.

## Discount Calculation

All pricing is server-side via `ReservationPricingService` and `DiscountEngine`:

```
subtotal = hourly_rate × duration
discount = calculate(promo, subtotal)
total    = max(subtotal - discount, 0)
```

### Percentage

```
discount = round(subtotal × value / 100)
if maximum_discount: discount = min(discount, maximum_discount)
discount = min(discount, subtotal)
```

### Fixed

```
discount = min(fixed_value, subtotal)
```

Currency must match reservation currency for fixed promos.

## Validation Rules

`PromoCodeValidator` checks:

- Code exists and is active
- Date window (`starts_at`, `ends_at`) using server UTC time
- Business eligibility (business promo must match; platform promos apply everywhere)
- Minimum amount
- Usage limit and per-user limit
- Currency compatibility (fixed only)

Invalid reasons returned to clients: `invalid_code`, `inactive`, `not_started`, `expired`, `usage_limit_reached`, `user_limit_reached`, `minimum_amount_not_reached`, `business_not_eligible`, `currency_not_supported`.

## Lifecycle

1. **Preview** — `POST /api/v1/businesses/{business}/promo-codes/validate` calculates subtotal and discount without consuming usage.
2. **Apply** — Reservation creation with optional `promo_code` validates, snapshots pricing, creates reservation, records redemption, increments `usage_count` atomically (row lock).
3. **Cancel** — Redemption status set to `cancelled`. Usage is **not** restored (documented default).

## Security

- Clients may only send `promo_code`; never `discount_amount` or `total_amount`.
- Business promo management scoped to owner/manager via `PromoCodePolicy`.
- Platform admin APIs use `promotions.view` / `promotions.manage` permissions.
- Cross-business IDOR prevented by `ensureBelongsToBusiness` checks.
- Concurrency: `lockForUpdate()` on promo row before incrementing `usage_count`.

## API Endpoints

### Customer

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/businesses/{business}/promo-codes/validate` | Preview promo |
| POST | `/api/v1/businesses/{business}/reservations` | Optional `promo_code` field |

### Business management

| Method | Path | Permission |
|--------|------|------------|
| GET | `/api/v1/manage/businesses/{business}/promo-codes` | Owner/Manager |
| POST | `/api/v1/manage/businesses/{business}/promo-codes` | Owner/Manager |
| GET/PATCH/DELETE | `.../promo-codes/{promo}` | Owner/Manager |

Staff cannot create or modify promos.

### Platform admin

| Method | Path | Permission |
|--------|------|------------|
| GET/POST | `/api/v1/admin/promo-codes` | promotions.view / manage |
| GET/PATCH/DELETE | `/api/v1/admin/promo-codes/{promo}` | promotions.view / manage |

Platform promos omit `business_id` or set it for business-specific admin-created promos.

## Payment Integration

`PaymentService` uses `reservation.total_amount`, which already reflects the discount. No client-supplied payment amount is trusted.

## Cancellation & Refunds

When a reservation with a promo is cancelled:

- Redemption record is marked `cancelled`
- `usage_count` is **not** decremented
- Historical reservation snapshot is preserved

Future refund handling should follow the same principle unless product policy explicitly requires promo restoration.

## Future Extension Points

`metadata` JSON and `DiscountEngine` context support future rules without schema changes:

- First-booking discounts
- Category/resource-specific promos
- Time-window and seasonal campaigns
- Loyalty and referral discounts
- Automatic discount application

Not implemented in this foundation.

## Intentionally Not Implemented

- Promo stacking
- Public promo discovery/listing
- Customer promo history endpoint
- Loyalty/referral systems
- Refund-driven usage restoration
- Marketing notifications
- Analytics dashboards
