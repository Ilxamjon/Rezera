# Check-in, Check-out & On-site Operations

This document describes the Rezera on-site reservation operations module (Prompt #19).

## Architecture Decision

**Reservation lifecycle** (booking status) and **operational session** (physical usage) are separate:

| Layer | Storage | Purpose |
|-------|---------|---------|
| Reservation status | `reservations.status` | Booking lifecycle (`confirmed`, `checked_in`, `completed`, `no_show`, etc.) |
| Operational session | `reservation_sessions` | Actual on-site usage with start/end timestamps |
| Check-in metadata | `reservations` | `checked_in_at`, `checked_out_at`, methods, actors |

`reservation_sessions` provides audit trail, actual duration, and overtime tracking without replacing the existing reservation state machine.

## Check-in Rules

`CheckInEligibilityService` validates:

1. Reservation exists and is `confirmed`
2. Not cancelled, completed, or no-show
3. Not already checked in
4. Resource is bookable (active, not maintenance)
5. Current time within check-in window (business timezone):
   - **Early:** `start_at - check_in_early_minutes` (from `booking_policies`, default 15)
   - **Late:** `end_at + no_show_grace_minutes` (default 20)

## Check-in Methods

| Method | Actor | Endpoint |
|--------|-------|----------|
| `customer` | Customer | `POST /api/v1/me/reservations/{id}/check-in` |
| `qr` | Customer/Staff | `.../check-in/qr` |
| `staff` | Business staff | `POST /api/v1/manage/businesses/{business}/reservations/{id}/check-in` |

All paths call `CheckInReservationAction`.

## QR Architecture

**Resource QR tokens** (`resource_qr_tokens`):

- Cryptographically random 48-character token (not database IDs)
- Scoped to `business_id` + `resource_id`
- Revocable; one active token per resource
- Validated against reservation's resource (prevents cross-resource use)

QR payload format: `rezera://check-in/{token}`

Customer scans QR at PC/table → app sends `qr_token` → server validates token matches reserved resource.

## Check-out

`CheckOutReservationAction`:

1. Requires active session
2. Closes session with actual duration and overtime metadata
3. Transitions reservation to `completed` via `ChangeReservationStatusAction`
4. Sets `checked_out_at`, `check_out_method`, `checked_out_by_user_id`

**Pricing:** Original reservation price snapshot is preserved. Overtime is recorded in session metadata only (no automatic billing).

## Operational Status

Derived by `ReservationOperationalStatusResolver`:

- `scheduled` — before check-in window
- `eligible_for_check_in` — within window
- `checked_in` — active session
- `checked_out` — completed with session ended
- `expired` — past window without check-in
- `cancelled` — cancelled/rejected

## Concurrency

- Row lock on reservation during check-in/check-out
- Partial unique index: one `active` session per reservation
- Idempotent check-in returns existing state if already checked in

## No-show

Use existing `PATCH .../reservations/{id}` with `status: no_show` via `ChangeReservationStatusAction`. Checked-in reservations cannot become no-show (existing transition rules).

## API Endpoints

### Customer
- `POST /api/v1/me/reservations/{reservation}/check-in`
- `POST /api/v1/me/reservations/{reservation}/check-in/qr`
- `POST /api/v1/me/reservations/{reservation}/check-out`

### Business
- `POST /api/v1/manage/businesses/{business}/reservations/{reservation}/check-in`
- `POST /api/v1/manage/businesses/{business}/reservations/{reservation}/check-in/qr`
- `POST /api/v1/manage/businesses/{business}/reservations/{reservation}/check-out`
- `GET /api/v1/manage/businesses/{business}/operations/today`
- `GET /api/v1/manage/businesses/{business}/sessions/active`
- `GET /api/v1/manage/businesses/{business}/resources/occupancy`
- `GET/POST /api/v1/manage/businesses/{business}/resources/{resource}/qr`
- `POST /api/v1/manage/businesses/{business}/resources/{resource}/qr/revoke`

## Events & Notifications

- `ReservationCheckedIn` / `ReservationCheckedOut` events (after commit)
- Notifications: `reservation_checked_in`, `reservation_checked_out`, `business_customer_checked_in`, `business_customer_checked_out`
- Audit logs: `reservation.checked_in`, `reservation.checked_out`

## Security

- Multi-tenant isolation via business scoping and `ensureBelongsToBusiness`
- QR tokens never expose internal IDs
- Client cannot submit timestamps
- Rate limiting on QR check-in (`throttle:booking`)

## Future Integrations

- Automatic no-show scheduler
- Overtime billing via PricingEngine
- Reservation QR (separate from resource QR)
- Loyalty/analytics listeners
