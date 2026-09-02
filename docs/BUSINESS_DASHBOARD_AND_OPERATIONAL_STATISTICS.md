# Business Dashboard & Operational Statistics

Module **#29** — a read-only operational snapshot for business owners, managers, and staff.

This module **extends** the existing lightweight dashboard (`BusinessDashboardService`) and **reuses** analytics, operations, and payment services. It does **not** introduce a competing statistics engine.

## Purpose

Give operators a fast “what is happening right now?” view:

- Today’s reservation counts by status
- Active check-in sessions
- Live resource availability (available / occupied / unavailable)
- Revenue collected today (owners & managers only)
- Next upcoming reservations

For historical BI, trends, and comparisons use `/analytics/*` (see [ANALYTICS_AND_BI.md](./ANALYTICS_AND_BI.md)).

## Architecture

| Layer | Responsibility |
|-------|----------------|
| `BusinessDashboardService` | Orchestrates today’s metrics |
| `ReservationAnalyticsService` | Reservation counts (overlap logic, business-local day) |
| `RevenueAnalyticsService` | Paid amounts & reservation value (same as analytics module) |
| `BusinessOperationsService` | Active sessions & live resource occupancy snapshot |
| `AnalyticsPolicy` | Operational vs financial visibility |

### Related endpoints (not duplicated)

| Endpoint | Use case |
|----------|----------|
| `GET /manage/businesses/{business}/dashboard` | **This module** — single-screen operational snapshot |
| `GET /manage/businesses/{business}/operations/today` | Full today list with reservations & sessions |
| `GET /manage/businesses/{business}/operations/active-sessions` | Active sessions only |
| `GET /manage/businesses/{business}/operations/resource-occupancy` | Per-resource live state |
| `GET /manage/businesses/{business}/analytics/dashboard` | Today / week / month BI with comparisons |

## Authorization

| Role | Dashboard access | Revenue today |
|------|------------------|---------------|
| Owner | ✅ | ✅ |
| Manager | ✅ | ✅ |
| Staff | ✅ | ❌ (`revenue_today` is `null`) |
| Customer | ❌ | ❌ |
| Platform admin | ✅ | ✅ |

Uses `AnalyticsPolicy::viewOperational` and `viewFinancial`.

## API

### `GET /api/v1/manage/businesses/{business}/dashboard`

**Query parameters**

| Parameter | Description |
|-----------|-------------|
| `upcoming_limit` | Optional. Number of upcoming reservations to include (default `10`, max `25`). |

**Example response**

```json
{
  "data": {
    "business": {
      "id": "…",
      "name": "Cyber Arena",
      "timezone": "Asia/Tashkent"
    },
    "date": "2026-09-05",
    "generated_at": "2026-09-05T12:00:00+05:00",
    "today": {
      "total": 24,
      "pending": 2,
      "confirmed": 8,
      "checked_in": 2,
      "completed": 12,
      "cancelled": 2,
      "no_show": 0,
      "active_sessions": 8
    },
    "upcoming": 7,
    "operations": {
      "active_sessions": 8,
      "overdue_check_ins": 1
    },
    "resources": {
      "total": 22,
      "available": 12,
      "occupied": 8,
      "maintenance": 1,
      "inactive": 1,
      "unavailable": 2
    },
    "revenue_today": {
      "currency": "UZS",
      "amount_paid": 1250000,
      "net_reservation_value": 1300000,
      "gross_reservation_value": 1350000,
      "discounts": 50000,
      "estimated_outstanding": 120000
    },
    "upcoming_reservations": [
      {
        "id": "…",
        "reservation_number": "RZ-20260905-000123",
        "start_at": "2026-09-05T09:00:00Z",
        "end_at": "2026-09-05T11:00:00Z",
        "start_time": "14:00",
        "end_time": "16:00",
        "duration_minutes": 120,
        "status": "confirmed",
        "resource": {
          "id": "…",
          "name": "PS5 VIP",
          "code": "PS5-VIP"
        },
        "customer_name": "Jamshid"
      }
    ]
  }
}
```

### Backward compatibility

Existing clients that only read `data.today` and `data.upcoming` continue to work. New fields are additive.

## Metric definitions

### Today’s reservations

Reservations whose interval **overlaps** the current business-local calendar day (`start_at < end_of_day` AND `end_at > start_of_day`). Same overlap logic as analytics and booking management.

### Upcoming count

Occupying reservations (`pending`, `confirmed`, `checked_in`) with `end_at > now()`.

### Upcoming list

Future occupying reservations with `start_at > now()`, ordered by `start_at`, limited by `upcoming_limit`.

### Active sessions

Count of `reservation_sessions` with `status = active` for the business.

### Overdue check-ins

Today’s `confirmed` reservations past `end_at` without `checked_in_at`.

### Resource snapshot

Per active resource:

- **occupied** — has an active session
- **available** — active status, no active session
- **unavailable** — `maintenance` + `inactive`

### Revenue today

Delegated to `RevenueAnalyticsService` for the `today` preset:

- `amount_paid` — sum of `paid` payments with `paid_at` (or `created_at` fallback) in today’s range
- `net_reservation_value` — sum of reservation `total_amount` overlapping today

Staff receive `revenue_today: null`.

## Configuration

`config/business_dashboard.php`:

- `upcoming_limit_default` — default preview size (env `REZERA_DASHBOARD_UPCOMING_LIMIT`)
- `upcoming_limit_max` — hard cap for `?upcoming_limit=` (env `REZERA_DASHBOARD_UPCOMING_LIMIT_MAX`)

## Tests

`tests/Feature/Api/V1/Business/BusinessDashboardTest.php`

- Aggregate counts & backward-compatible fields
- Active sessions, resource snapshot, upcoming list
- Staff vs owner revenue visibility
- Customer forbidden

## Future extensions

- Widget toggles / customizable layout
- Alerts (overdue, low availability)
- Cached snapshots for very large venues
- WebSocket push for live session counts
