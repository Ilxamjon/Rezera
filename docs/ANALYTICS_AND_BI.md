# Analytics & Business Intelligence

## Overview

The Rezera analytics module provides **read-only**, business-scoped operational and financial insights derived from authoritative domain data:

- Reservations
- Payments
- Reservation sessions (check-in/check-out)
- Resources & categories
- Loyalty ledger
- Promo redemptions
- Referrals (platform-scoped, business-attributed where possible)

Analytics **never** mutates domain state and **never** recalculates pricing or payments.

## Architecture

```
Authoritative data (reservations, payments, sessions, loyalty_transactions)
    ↓
Analytics services (SQL aggregation)
    ↓
Manage API (/api/v1/manage/businesses/{business}/analytics/*)
```

### Core components

| Component | Responsibility |
|-----------|----------------|
| `AnalyticsDateRange` | Preset/custom ranges in business timezone → UTC query bounds |
| `AnalyticsFilter` | Shared filters (resource, category, status, granularity) |
| `AnalyticsMetrics` | Safe rates, averages, period comparisons |
| `ReservationAnalyticsService` | Reservation counts, rates, trends |
| `RevenueAnalyticsService` | Reservation value vs payment value |
| `ResourceAnalyticsService` | Utilization, ranking, categories |
| `CustomerAnalyticsService` | New/returning customers, top customers |
| `CheckInAnalyticsService` | Check-in rates, delays, overtime |
| `LoyaltyAnalyticsService` | Points ledger aggregates |
| `PromotionAnalyticsService` | Promo usage and discounts |
| `BusinessAnalyticsService` | Overview & dashboard orchestration |

## Data sources

| Metric family | Source of truth |
|---------------|-----------------|
| Reservation counts | `reservations` (overlap with date range) |
| Reservation value | `reservations.subtotal_amount`, `discount_amount`, `total_amount` |
| Paid/refunded amounts | `payments` (`paid_at`, `status`, `amount`) |
| Utilization | `reservations.duration_minutes` vs `business_hours` × active resources |
| Check-in metrics | `reservations.checked_in_at`, `reservation_sessions` |
| Loyalty | `loyalty_transactions` ledger |
| Promotions | `promo_code_redemptions`, reservation promo fields |

## Metric definitions

### Reservation value

- **Gross reservation value** — `sum(subtotal_amount)`
- **Discounts** — `sum(discount_amount)`
- **Net reservation value** — `sum(total_amount)` (after discounts)

### Payment value

- **Amount paid** — `sum(payments.amount)` where `status = paid`
- **Amount refunded** — payments with `refunded` / `partially_refunded` status
- **Estimated outstanding** — reservations with `payment_status` unpaid/pay_at_venue and qualifying status

> Net reservation value is **not** the same as cash received. Paid amount comes from the payment ledger.

### Utilization

```
utilization % = booked_minutes / available_minutes × 100
```

- **Booked minutes** — sum of `duration_minutes` for confirmed/checked_in/completed reservations overlapping the period
- **Available minutes** — active resources × open minutes per business day (from `business_hours`, excluding closed days; overnight hours supported)

### Customers

- **Active customer** — at least one **completed** reservation during the period
- **New customer** — first **completed** reservation `completed_at` falls inside the period
- **Returning customer** — had a completed reservation before the period **and** completed one during the period

### Rates

- **Check-in rate** — `(checked_in + completed) / (completed + checked_in + no_show)`
- **No-show rate** — `no_show / eligible`
- **Cancellation rate** — `cancelled / total`

Division by zero returns `null`.

## Date ranges

Presets: `today`, `yesterday`, `last_7_days`, `last_30_days`, `this_month`, `last_month`, `custom`.

All presets interpret calendar boundaries in the **business timezone**, then convert to UTC for queries.

Maximum custom range: **366 days**.

## Comparison periods

Overview supports:

- explicit `compare_from` / `compare_to`
- automatic previous period of equal length (`compare_previous=true`, default)

Returns `current`, `previous`, `change_absolute`, `change_percent` (`null` when previous = 0).

## Permissions

| Role | Operational analytics | Financial analytics |
|------|----------------------|---------------------|
| Owner | ✅ | ✅ |
| Manager | ✅ | ✅ |
| Staff | ✅ | ❌ |
| Customer | ❌ | ❌ |
| Platform admin | ✅ | ✅ |

Financial endpoints: `/revenue`, `/top-customers`, `/loyalty`, `/promotions`.

## API endpoints

Base: `/api/v1/manage/businesses/{business}/analytics`

| Endpoint | Description |
|----------|-------------|
| `GET /overview` | Summary metrics + optional comparison |
| `GET /dashboard` | Today / week / month snapshot |
| `GET /reservations` | Metrics + trends |
| `GET /revenue` | Financial metrics + trends |
| `GET /resources` | Summary + per-resource breakdown |
| `GET /resources/ranking` | Ranked resources (whitelisted sort) |
| `GET /resource-categories` | Category aggregates |
| `GET /customers` | Customer metrics |
| `GET /top-customers` | Top customers by value/count |
| `GET /peak-hours` | Weekday/hour activity |
| `GET /weekdays` | Day-of-week breakdown |
| `GET /hourly-occupancy` | Hourly booked minutes |
| `GET /check-ins` | Check-in & session analytics |
| `GET /loyalty` | Loyalty ledger aggregates |
| `GET /promotions` | Promo usage |
| `GET /referrals` | Business-attributed referral activity |

### Admin

`GET /api/v1/admin/analytics/overview` — platform-level summary (UTC).

## Performance

- Aggregations use grouped SQL, not PHP loops over full datasets
- Indexes added:
  - `payments (business_id, status, paid_at)`
  - `loyalty_transactions (business_id, created_at)`
  - `reservation_sessions (business_id, started_at)`
- No caching in this phase (correctness first)

## Privacy

Top customer endpoints return only `customer_id` and `name`. No tokens, credentials, or cross-business data.

## Future extensions

- Cohort / CLV analysis
- Pre-aggregated warehouse tables
- CSV/PDF export via `AnalyticsExporterInterface`
- Forecasting & demand prediction
- Platform-wide BI dashboards

## Relationship to existing dashboard

`GET /manage/businesses/{business}/dashboard` remains the lightweight **operational today** snapshot (Module #29). It reuses analytics services for counts and revenue but returns a single-screen live view (sessions, resource occupancy, upcoming list). See [BUSINESS_DASHBOARD_AND_OPERATIONAL_STATISTICS.md](./BUSINESS_DASHBOARD_AND_OPERATIONAL_STATISTICS.md).

`/analytics/*` provides full historical BI capabilities.
