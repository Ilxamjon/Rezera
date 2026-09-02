# Advanced Pricing Engine

This document describes the Rezera advanced pricing foundation introduced in Prompt #18.

## Overview

The pricing engine extends the base resource rate (`resources.hourly_rate_amount`) with configurable **pricing rules** for:

- Time-of-day windows (peak/off-peak)
- Weekday pricing (including weekends)
- Date-specific overrides (holidays, events)
- Resource-specific, category-specific, and business-wide scope
- Hourly and fixed reservation pricing

All amounts are integer **UZS** minor units. Calculations are server-side only.

## Architecture

```
PricingContext
      ↓
PricingEngine
      ├─ PricingRuleResolver   (rule matching & precedence)
      ├─ PricingIntervalSplitter (boundary segmentation)
      └─ base rate fallback
      ↓
PricingResult (subtotal + segments + snapshot)
      ↓
DiscountEngine / PromoCodeValidator (Prompt #17)
      ↓
Final reservation total
```

**Single authoritative path:** `PricingEngine::calculate()` is used by reservation creation, customer preview, business preview, and promo validation.

## Pricing Hierarchy

When multiple rules could apply to a time segment, selection order is:

1. **Specificity score** (higher wins):
   - Specific date (`specific_date`) — +1000
   - Date range (`starts_at` / `ends_at`) — +800
   - Weekday (`day_of_week`) — +100
   - Time window (`start_time` / `end_time`) — +50
   - Scope: resource (+30) > category (+20) > business (+10)
2. **Priority** field (higher wins)
3. **Rule ID** (deterministic tie-break)

### Base fallback (no matching hourly rule)

1. `resources.hourly_rate_amount`
2. `resource_groups.default_hourly_rate_amount` (category default)
3. Validation error if neither exists

Resource-specific price always beats category default.

## Rule Types

| Type | Behavior |
|------|----------|
| `hourly` | Price × segment duration / 60, summed across segments |
| `fixed` | Single charge when the **entire** reservation falls within the rule's date/time scope |

Fixed pricing does not multiply by hours.

## Time Segmentation

Reservations crossing pricing boundaries are split automatically.

Example (Friday):

| Window | Rate |
|--------|------|
| 09:00–17:00 | 30,000/hr |
| 17:00–23:00 | 50,000/hr |

Reservation **16:00–19:00**:

```
16:00–17:00 → 30,000  (1 hour)
17:00–19:00 → 100,000 (2 hours × 50,000)
Subtotal    → 130,000
```

The engine does **not** apply only the start-time rate to the full duration.

## Overnight Handling

Intervals use the business timezone (`businesses.timezone`, default `Asia/Tashkent`).

- Local datetime resolution via `ReservationIntervalResolver` / `TimeInterval`
- Overnight reservations (e.g. 23:00–01:00) are handled on the correct calendar day
- Time windows where `end_time <= start_time` are treated as overnight windows

## Promo Integration

Pricing flow:

```
Advanced Pricing → subtotal → Promo validation → discount → total
```

Promo codes never alter base segment calculation. Discount applies to the authoritative subtotal.

Preview endpoints do **not** consume promo usage.

## Snapshots

Reservations store:

| Field | Purpose |
|-------|---------|
| `subtotal_amount` | Pre-discount total |
| `discount_amount` | Promo discount |
| `total_amount` | Final amount |
| `hourly_rate_amount` | Resource base rate at booking time |
| `pricing_snapshot` | JSON: segments, applied rules, promo info |

Historical reservations are unaffected by later rule or base price changes.

## Data Model

### `pricing_rules`

Scoped by `business_id` with optional `resource_id` or `resource_group_id` (not both).

Indexes on business, resource, category, active state, dates, weekday/time, priority.

### `resource_groups`

Optional `default_hourly_rate_amount` and `default_currency` for category fallback.

## API Endpoints

### Customer

| Method | Path |
|--------|------|
| POST | `/api/v1/businesses/{business}/pricing/preview` |

Optional `promo_code`. Returns `currency`, `subtotal`, `discount`, `total`, `segments`.

### Business management

| Method | Path |
|--------|------|
| GET/POST/PATCH/DELETE | `/api/v1/manage/businesses/{business}/pricing-rules[/{rule}]` |
| POST | `/api/v1/manage/businesses/{business}/pricing/preview` |

Owner/manager only. Audit logs: `pricing_rule.created`, `pricing_rule.updated`, `pricing_rule.deleted`.

### Platform admin

| Method | Path |
|--------|------|
| GET | `/api/v1/admin/pricing-rules` |
| GET | `/api/v1/admin/pricing-rules/{rule}` |

Read-only visibility (`pricing.view` permission).

## Security

- Client cannot submit `subtotal`, `total`, `unit_price`, or `discount`
- Resource/category references validated against business ownership
- Cross-business IDOR prevented via scoped queries and `ensureBelongsToBusiness`
- Reservation creation recalculates price; preview is informational only

## Money Safety

- Integer arithmetic only (no floats)
- `round()` for hourly segment amounts
- `total = max(subtotal - discount, 0)`
- Negative prices rejected at DB and validation layers

## Performance

- Rules loaded once per calculation, scoped by business + resource + category
- Indexed queries; no full-table scans
- No pricing result caching (correctness over micro-optimization)

## Future Extension Points

`metadata` JSON and scope fields support future:

- Package pricing
- Minimum duration charges
- Seasonal campaigns tied to pricing
- Dynamic/surge pricing (not implemented)

## Intentionally Not Implemented

- AI / ML pricing
- Surge / demand pricing
- Currency conversion
- Loyalty/referral discounts in pricing layer
- Payment provider integration
- Analytics dashboards
- Flutter UI
