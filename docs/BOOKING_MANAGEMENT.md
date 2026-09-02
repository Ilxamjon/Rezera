# Rezera — Booking Management & Business Operations

**Status:** Implemented (Prompt #10)  
**Depends on:** `docs/RESERVATION_ENGINE.md`

---

## 1. Overview

Prompt #10 extends the reservation engine with operational booking management for customers and businesses. A single `Reservation` model represents one booking.

```
Reservation Engine (create/conflict)
        ↓
Booking Management (view/filter/operate)
        ↓
Future: payments, notifications, check-in
```

---

## 2. Customer booking APIs

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/me/reservations` | List own reservations |
| `GET` | `/api/v1/me/reservations/upcoming` | Upcoming active reservations |
| `GET` | `/api/v1/me/reservations/{reservation}` | Reservation detail |
| `POST` | `/api/v1/me/reservations/{reservation}/cancel` | Cancel eligible reservation |

### Customer list filters

| Parameter | Description |
|---|---|
| `status` | Filter by reservation status |
| `business_id` | Filter by business |
| `date` | Filter by local reservation date (`Y-m-d`) |
| `scope` | `upcoming` or `past` |
| `sort` | `start_at` (allowlist) |
| `direction` | `asc` or `desc` |
| `per_page` | 1–50 (default 15) |

**Default sort:** `start_at` ascending (upcoming first).

**Upcoming scope:** `end_at > now()` and status in `pending`, `confirmed`, `checked_in`.

**Past scope:** `end_at <= now()` or terminal statuses (`cancelled`, `completed`, `no_show`, `rejected`, `expired`).

### Customer privacy

Customer responses use `ReservationResource` and do **not** expose internal business-only fields or other customers' data.

---

## 3. Business booking APIs

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/manage/businesses/{business}/reservations` | Paginated reservation list |
| `GET` | `/api/v1/manage/businesses/{business}/reservations/today` | Today's reservations |
| `GET` | `/api/v1/manage/businesses/{business}/reservations/upcoming` | Upcoming reservations |
| `GET` | `/api/v1/manage/businesses/{business}/reservations/{reservation}` | Reservation detail |
| `PATCH` | `/api/v1/manage/businesses/{business}/reservations/{reservation}` | Change status |
| `POST` | `/api/v1/manage/businesses/{business}/reservations/{reservation}/cancel` | Cancel with reason |
| `GET` | `/api/v1/manage/businesses/{business}/dashboard` | Operational summary |
| `GET` | `/api/v1/manage/businesses/{business}/calendar/day` | Daily resource calendar |
| `GET` | `/api/v1/manage/businesses/{business}/calendar/week` | Weekly schedule summary |
| `GET` | `/api/v1/manage/businesses/{business}/calendar/timeline` | Reservation timeline |
| `GET` | `/api/v1/manage/businesses/{business}/calendar/resources/{resource}` | Resource schedule |

### Business list filters

| Parameter | Description |
|---|---|
| `status` | Filter by status |
| `resource_id` | Filter by resource (must belong to business) |
| `date` | Business-local date (`Y-m-d`) or `today` |
| `from` / `to` | Date range (max 90 days) |
| `search` | Reservation number, customer name, or phone |
| `scope` | `today`, `upcoming`, or `past` |
| `sort` | `start_at` |
| `direction` | `asc` or `desc` |
| `per_page` | 1–50 |

**Sorting defaults:**

- `today` / `upcoming`: `start_at` ASC
- `past` / date range: `start_at` DESC

### Business detail response

Uses `ReservationManagementResource` with:

- reservation number, status, resource + category
- customer object (`id`, `name`, `phone`)
- local date/time, duration, pricing
- notes, cancellation info, status timestamps

---

## 4. Status lifecycle

Uses `ReservationTransitionService` from Prompt #9.

| From | Allowed to |
|---|---|
| `pending` | `confirmed`, `rejected`, `cancelled`, `expired` |
| `confirmed` | `cancelled`, `completed`, `checked_in`, `no_show` |
| `checked_in` | `completed`, `no_show` |
| Terminal | none |

Invalid transitions return **422** with validation error on `status`.

### Status timestamps

| Field | Set when |
|---|---|
| `confirmed_at` | `confirmed` |
| `checked_in_at` | `checked_in` |
| `completed_at` | `completed` |
| `no_show_at` | `no_show` |
| `cancelled_at` | `cancelled` |

---

## 5. Cancellation

### Customer

- May cancel own `pending` or `confirmed` reservations
- Cannot cancel `completed`, `cancelled`, or `no_show`
- Optional `reason` (max 500 chars)
- Idempotent: cancelling an already-cancelled reservation returns the current state

### Business

- Owner/Manager may cancel eligible reservations via `POST .../cancel`
- Stores `cancellation_reason`, `cancelled_at`, actor metadata
- Reservation record is preserved (not deleted)

---

## 6. Authorization

| Role | View | Confirm/Reject | Cancel | Complete/No-show |
|---|---|---|---|---|
| Owner | ✓ | ✓ | ✓ | ✓ |
| Manager | ✓ | ✓ | ✓ | ✓ |
| Staff | ✓ | ✗ | ✗ | ✓ |

Implemented via:

- `BusinessAuthorizationService::canOperateBookings()` — view + operational changes
- `BusinessAuthorizationService::canAdministerBookings()` — confirm/reject/cancel
- `ReservationPolicy::changeStatus()` — per-target-status authorization

Cross-tenant access returns **404** when reservation does not belong to the business in the URL.

---

## 7. Timezone behavior

All business date filters use `businesses.timezone` (default `Asia/Tashkent`).

A reservation belongs to a business-local date when it **overlaps** that day:

```
start_at < end_of_day_utc AND end_at > start_of_day_utc
```

This correctly includes overnight reservations spanning midnight.

"Today" and dashboard counts use the same overlap logic in business local time.

---

## 8. Dashboard summary

`GET /api/v1/manage/businesses/{business}/dashboard`

Operational snapshot for owners, managers, and staff. See [BUSINESS_DASHBOARD_AND_OPERATIONAL_STATISTICS.md](./BUSINESS_DASHBOARD_AND_OPERATIONAL_STATISTICS.md) for the full schema.

```json
{
  "data": {
    "business": { "id": "…", "name": "Cyber Arena", "timezone": "Asia/Tashkent" },
    "date": "2026-09-05",
    "generated_at": "2026-09-05T12:00:00+05:00",
    "today": {
      "total": 12,
      "pending": 2,
      "confirmed": 8,
      "checked_in": 0,
      "completed": 1,
      "cancelled": 1,
      "no_show": 0,
      "active_sessions": 3
    },
    "upcoming": 7,
    "operations": {
      "active_sessions": 3,
      "overdue_check_ins": 0
    },
    "resources": {
      "total": 20,
      "available": 12,
      "occupied": 6,
      "maintenance": 1,
      "inactive": 1,
      "unavailable": 2
    },
    "revenue_today": {
      "currency": "UZS",
      "amount_paid": 1250000,
      "net_reservation_value": 1300000
    },
    "upcoming_reservations": []
  }
}
```

`revenue_today` is `null` for staff. Optional `?upcoming_limit=` (max 25) controls the preview list size.

Uses database `COUNT` + `GROUP BY status` — no full-table loading in PHP.

---

## 9. Search

`?search=` performs case-insensitive (`ilike`) matching on:

- `reservation_number`
- `customer_name_snapshot`
- `customer_phone_snapshot`

Legacy `?reservation_number=` is mapped to `search`.

---

## 10. Domain events

After successful commit, `ReservationStatusChanged` is dispatched for status changes and cancellations. This provides a foundation for future notifications and analytics without sending external messages inside transactions.

Reservation history is also recorded in `reservation_events`.

---

## 11. Error responses

| Situation | Code |
|---|---|
| Validation | 422 |
| Unauthorized | 401 |
| Forbidden (policy) | 403 |
| Cross-tenant not found | 404 |
| Booking conflict | 409 |
| Invalid status transition | 422 |

---

## 12. Example requests

### Today's bookings

```http
GET /api/v1/manage/businesses/{id}/reservations/today
Authorization: Bearer {token}
```

### Confirm pending reservation

```http
PATCH /api/v1/manage/businesses/{id}/reservations/{id}
Content-Type: application/json

{ "status": "confirmed" }
```

### Customer upcoming

```http
GET /api/v1/me/reservations/upcoming
Authorization: Bearer {token}
```

---

## 13. Future modules (not implemented)

- Payments (Payme, Click, Stripe)
- Push/SMS/Telegram notifications
- QR check-in
- Automatic no-show scheduler
- Advanced analytics and CRM
- Loyalty and coupons

---

## 14. Key implementation files

| Area | Path |
|---|---|
| Query builder | `app/Services/Reservations/ReservationQueryBuilder.php` |
| Filters DTO | `app/Services/Reservations/ReservationListFilters.php` |
| Dashboard | `app/Services/Reservations/BusinessDashboardService.php` |
| Policy | `app/Policies/ReservationPolicy.php` |
| Status transitions | `app/Services/Reservations/ReservationTransitionService.php` |
| Business controller | `app/Http/Controllers/Api/V1/Manage/ReservationController.php` |
| Customer controller | `app/Http/Controllers/Api/V1/Me/ReservationController.php` |
| Tests | `tests/Feature/Api/V1/Reservation/BookingManagementTest.php` |
