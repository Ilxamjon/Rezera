# Rezera — Availability Engine

**Status:** Implemented (Prompt #8)  
**Depends on:** `docs/BUSINESS_MANAGEMENT.md`, `docs/RESOURCE_MANAGEMENT.md`, `docs/DATABASE-ARCHITECTURE.md`

---

## 1. Purpose

The Availability Engine **reads** business hours, resource status, and occupying reservations to answer:

> For business B, on date D, between start and end — which resources are available?

It does **not** create reservations. It is a **read/calculate** service only.

**Critical:** Availability is an observation, not a lock. A resource shown as available can be booked by another customer before the client completes checkout. The future Reservation Engine must perform its own atomic conflict check inside a transaction.

---

## 2. Architecture

```
AvailabilityController
        ↓
CheckAvailabilityRequest (validation)
        ↓
AvailabilityEngine
        ↓
BusinessHoursResolver → open intervals (incl. overnight)
        ↓
Resource query (status filters)
        ↓
OccupyingReservationProvider → blocking intervals
        ↓
TimeInterval overlap logic
        ↓
AvailabilityResponseResource
```

### Core classes

| Class | Role |
|---|---|
| `TimeInterval` | Half-open `[start, end)` interval with overlap + coverage checks |
| `BusinessHoursResolver` | Converts `business_hours` rows into local open intervals |
| `OccupyingReservationProvider` | Loads `pending`, `confirmed`, `checked_in` reservations |
| `AvailabilityEngine` | Orchestrates request-level + per-resource evaluation |
| `AvailabilityQuery` | Input DTO |
| `AvailabilityResult` | Output DTO |

### Extension point (future)

`resource_unavailability` blocked periods can be added as another provider feeding the same overlap loop without rewriting the engine.

---

## 3. Input parameters

### Public

**`GET /api/v1/businesses/{business}/availability`**

| Parameter | Required | Description |
|---|---|---|
| `date` | Yes | `Y-m-d` in business-local context |
| `start_time` | Yes | `H:i` (24h) |
| `end_time` | Yes | `H:i` — if ≤ start, crosses midnight to next day |
| `category_id` | No | Filter by resource group UUID |
| `resource_category_id` | No | Alias for `category_id` |

**`GET /api/v1/businesses/{business}/resources/{resource}/availability`**

Same query params (no `resource_id` needed).

### Management

**`GET /api/v1/manage/businesses/{business}/availability`**

Same parameters + `include_unavailable`. Requires `auth:sanctum` and business membership. Returns inactive/maintenance resources with `reason`.

---

## 4. Output structure

```json
{
  "success": true,
  "data": {
    "business": { "id": "uuid", "name": "Cyber Arena", "timezone": "Asia/Tashkent" },
    "date": "2026-09-04",
    "start_time": "20:00",
    "end_time": "22:00",
    "request_status": "available",
    "resources": [
      {
        "id": "uuid",
        "name": "PC #01",
        "code": "PC-01",
        "resource_type": "pc",
        "category": { "id": "uuid", "name": "Gaming PC" },
        "status": "available",
        "price": 30000,
        "price_unit": "hour",
        "currency": "UZS"
      },
      {
        "id": "uuid",
        "name": "PC #02",
        "status": "unavailable",
        "reason": "booked",
        "price": 30000,
        "price_unit": "hour"
      }
    ]
  }
}
```

Public responses include `reason` only when status is `unavailable`. Management always includes `reason`.

---

## 5. Working hours integration

Uses `business_hours` from Prompt #6:

- **Closed day** → `request_status: business_closed`, all resources unavailable
- **24h** → full-day open interval
- **Normal** → `[opens_at, closes_at)` on that date
- **Overnight** (`closes_at < opens_at`) → `[opens, 24:00)` + `[00:00, closes)` next calendar day
- **Spillover** → previous weekday's overnight contributes `[00:00, closes)` on current date

The requested interval must be **fully contained** within the union of open intervals. Partial overlap with closed gaps → `outside_working_hours`.

---

## 6. Overnight hours

Example: Friday hours `18:00–02:00`

Open intervals for Friday 2026-09-04:

1. `2026-09-04 18:00` → `2026-09-05 00:00`
2. `2026-09-05 00:00` → `2026-09-05 02:00`

| Request | Result |
|---|---|
| `23:00–01:00` | Available (if no blocks) |
| `01:00–03:00` | Outside working hours (only 01:00–02:00 is open) |
| `09:00–11:00` | Outside working hours |

---

## 7. Resource statuses

| DB status | Public API | Reason |
|---|---|---|
| `active` | Included | Evaluated against hours + reservations |
| `inactive` | Hidden | `inactive` (management only) |
| `maintenance` | Hidden | `maintenance` (management only) |

---

## 8. Interval-overlap algorithm

Half-open intervals `[start, end)`:

```
overlap = startA < endB AND startB < endA
```

Touching boundaries do **not** overlap:

- Existing `18:00–20:00`, request `20:00–22:00` → **available**
- Existing `18:00–20:00`, request `19:00–21:00` → **booked**

Reservation blocking includes `buffer_minutes_applied` on the end instant.

### Occupying reservation statuses

| Status | Blocks? |
|---|---|
| `pending` | Yes |
| `confirmed` | Yes |
| `checked_in` | Yes |
| `cancelled`, `rejected`, `expired`, `completed`, `no_show` | No |

---

## 9. Business timezone

All local times use `businesses.timezone` (default `Asia/Tashkent`). Date parameter is interpreted in business local time. Past-time validation compares against `Carbon::now($timezone)`.

---

## 10. Past-time rules

- Past dates → validation error
- Today's interval entirely in the past → validation error on `start_time`
- Future dates → no past-time restriction

---

## 11. Concurrency warning

```
Availability ≠ Reservation lock
```

Two customers may both see a slot as available. Only the Reservation Engine (Prompt #9) with a DB transaction + EXCLUDE constraint guarantees exclusivity.

---

## 12. Performance

- Single resource query with eager-loaded `group`
- Single reservation query filtered by `resource_id`, `status`, and UTC time bounds
- No caching in MVP (correctness first)
- Recommended future index: `reservations (resource_id, start_at)` — already partially indexed

---

## 13. Future Booking Engine contract (Prompt #9)

The Reservation Engine will:

1. Receive resource + requested interval
2. **Start database transaction**
3. Re-check occupying reservations (with `SELECT FOR UPDATE` or rely on EXCLUDE)
4. Verify resource `status = active`
5. Verify working hours
6. Verify booking policy rules (duration, advance window)
7. Create reservation with price snapshot
8. **Commit**
9. Return reservation

Availability Engine remains read-only and is **not** a substitute for step 3.

---

## 14. Tests

| File | Coverage |
|---|---|
| `tests/Unit/Support/Time/TimeIntervalTest.php` | Overlap matrix, overnight intervals |
| `tests/Feature/Services/Availability/AvailabilityEngineTest.php` | Engine scenarios incl. game club |
| `tests/Feature/Api/V1/Availability/AvailabilityApiTest.php` | HTTP, auth, validation |

```bash
php artisan test --filter=Availability
```

---

## 15. Key files

| Path |
|---|
| `app/Support/Time/TimeInterval.php` |
| `app/Services/Availability/BusinessHoursResolver.php` |
| `app/Services/Availability/OccupyingReservationProvider.php` |
| `app/Services/Availability/AvailabilityEngine.php` |
| `app/Http/Controllers/Api/V1/AvailabilityController.php` |
| `app/Http/Controllers/Api/V1/Manage/AvailabilityController.php` |
| `app/Http/Requests/Api/V1/Availability/CheckAvailabilityRequest.php` |
| `app/Http/Resources/Api/V1/AvailabilityResponseResource.php` |
