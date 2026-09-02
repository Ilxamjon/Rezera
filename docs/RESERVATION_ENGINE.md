# Rezera — Reservation / Booking Engine

**Status:** Implemented (Prompt #9)  
**Depends on:** `docs/AVAILABILITY_ENGINE.md`, `docs/RESOURCE_MANAGEMENT.md`

---

## 1. Reservation architecture

```
Customer → POST /businesses/{business}/reservations
         → StoreReservationRequest
         → CreateReservationAction (transaction)
              ├── lock resource row (FOR UPDATE)
              ├── conflict query
              ├── AvailabilityEngine recheck
              ├── price snapshot
              └── PostgreSQL EXCLUDE constraint (final safety)
```

**Availability reads. Reservations write.** The create flow always re-validates before insert.

---

## 2. Database schema (existing + extension)

Uses existing `reservations` table with migration adding:

| Column | Purpose |
|---|---|
| `reservation_number` | Human-readable unique ID (`RZ-YYYYMMDD-XXXXXX`) |
| `customer_name_snapshot` | Historical customer name |
| `customer_phone_snapshot` | Historical customer phone |
| `cancelled_at` | Cancellation timestamp |

Core fields (already present):

- `customer_id`, `business_id`, `resource_id`
- `start_at`, `end_at`, `duration_minutes` (UTC instants)
- `occupancy_range` (tstzrange, half-open `[start, end+buffer)`)
- `status`, pricing snapshot, `idempotency_key`

---

## 3. Reservation statuses

| Status | Blocks resource? | Description |
|---|---|---|
| `pending` | Yes | Manual approval hold |
| `confirmed` | Yes | Active booking |
| `checked_in` | Yes | Customer on site |
| `cancelled` | No | Cancelled |
| `rejected` | No | Owner declined pending |
| `expired` | No | Pending timed out |
| `completed` | No | Finished session |
| `no_show` | No | Did not arrive |

---

## 4. Status transitions

| From | Allowed to |
|---|---|
| `pending` | `confirmed`, `rejected`, `cancelled`, `expired` |
| `confirmed` | `cancelled`, `completed`, `checked_in`, `no_show` |
| `checked_in` | `completed`, `no_show` |
| Terminal states | none |

Implemented in `ReservationTransitionService`.

---

## 5. Creation flow

1. Authenticate customer (Sanctum)
2. Validate request + business/resource tenancy
3. Normalize local date/time → UTC `start_at`/`end_at`
4. **BEGIN TRANSACTION**
5. `SELECT ... FOR UPDATE` on resource
6. `ReservationConflictService` query
7. `AvailabilityEngine` fresh recheck
8. Calculate price snapshot
9. Set status: `instant` policy → `confirmed`, `manual` → `pending`
10. Insert reservation + `reservation_events` row
11. **COMMIT**
12. On PostgreSQL EXCLUDE violation (`23P01`) → `409 booking_conflict`

---

## 6. Concurrency strategy

**Two layers:**

1. **Application:** `lockForUpdate()` on resource + conflict query inside transaction
2. **Database:** GiST EXCLUDE on `(resource_id, occupancy_range)` for occupying statuses

This prevents double booking even under concurrent POST requests.

---

## 7. Pricing snapshot

```php
total_amount = round(hourly_rate_amount * duration_minutes / 60)
```

Stored on reservation at creation. Future resource price changes do not affect historical bookings.

---

## 8. Idempotency

Header: `Idempotency-Key: <uuid>`

Same key + same customer returns the existing reservation without creating a duplicate.

---

## 9. API routes

### Customer (auth required)

| Method | Endpoint |
|---|---|
| POST | `/api/v1/businesses/{business}/reservations` |
| GET | `/api/v1/me/reservations` |
| GET | `/api/v1/me/reservations/{reservation}` |
| POST | `/api/v1/me/reservations/{reservation}/cancel` |

### Business management (auth + membership)

| Method | Endpoint |
|---|---|
| GET | `/api/v1/manage/businesses/{business}/reservations` |
| GET | `/api/v1/manage/businesses/{business}/reservations/{reservation}` |
| PATCH | `/api/v1/manage/businesses/{business}/reservations/{reservation}` |
| POST | `/api/v1/manage/businesses/{business}/reservations/{reservation}/cancel` |

Creation endpoint uses `throttle:booking` rate limiter.

---

## 10. Authorization

`ReservationPolicy`:

- **view:** customer owns OR business staff with `canManageBookings`
- **create:** active user + publicly visible business
- **cancel:** customer (pending/confirmed) OR business member
- **updateStatus:** business `canManageBookings`

---

## 11. Error responses

| Situation | HTTP | Code |
|---|---|---|
| Booking conflict | 409 | `booking_conflict` |
| Validation | 422 | `validation_failed` |
| Unauthorized | 401 | `unauthenticated` |
| Forbidden | 403 | `forbidden` |

---

## 12. Future integration points

- **Payments:** `payment_status` field ready (`pay_at_venue` default)
- **Notifications:** dispatch events after commit (`reservation.created`, etc.)
- **Check-in:** `checked_in` status + `checked_in_at` field
- **Booking rules:** `booking_policies` min/max duration (validation extension)

---

## 13. Tests

```bash
php artisan test --filter=Reservation
```

Includes creation, conflict, overnight, idempotency, cancellation, status transitions, privacy, and price snapshot tests.

---

## 14. Key files

| Type | Path |
|---|---|
| Actions | `app/Actions/Reservations/*` |
| Services | `app/Services/Reservations/*` |
| Policy | `app/Policies/ReservationPolicy.php` |
| Controllers | `ReservationController`, `Me/ReservationController`, `Manage/ReservationController` |
| Migration | `2026_09_02_000002_add_reservation_customer_fields.php` |
