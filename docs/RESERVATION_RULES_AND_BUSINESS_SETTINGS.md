# Reservation Rules & Business Settings

Centralized configuration and validation for how each Rezera business accepts and manages reservations.

## Architecture

Rezera reuses the existing **`booking_policies`** table (1:1 with `businesses`) as the authoritative settings store. This module adds services and APIs on top of that model rather than introducing a duplicate `business_settings` table.

```
BookingPolicy (DB)
        ↓
BusinessSettingsService          → CRUD, defaults, validation
        ↓
EffectiveReservationSettingsResolver → business + optional resource overrides
        ↓
ReservationRulesEngine           → duration, advance, limits, cancellation rules
        ↓
CreateReservationAction / CancelReservationAction
        ↓
AvailabilityEngine (conflicts via occupancy_range + buffer)
        ↓
PricingEngine / DiscountEngine
```

### Responsibility boundaries

| Component | Responsibility |
|-----------|----------------|
| `BusinessSettingsService` | Store/retrieve/normalize booking policy configuration |
| `EffectiveReservationSettingsResolver` | Resolve effective values (resource → business → config default) |
| `ReservationRulesEngine` | Validate reservation rules (not price, not availability slots) |
| `AvailabilityEngine` | Working hours, resource status, occupancy conflicts |
| `CreateReservationAction` | Orchestrate rules → availability → pricing → creation |
| `ReservationTransitionService` | Status machine (pending/confirmed/cancelled/…) |
| `PricingEngine` / `DiscountEngine` | Money calculation |

## Settings schema (`booking_policies`)

| Column | Purpose | Default (config) |
|--------|---------|------------------|
| `confirmation_mode` | `instant` = auto-confirm, `manual` = pending | `instant` |
| `min_duration_minutes` | Minimum booking length | 30 |
| `max_duration_minutes` | Maximum booking length | 480 |
| `duration_step_minutes` | Duration/start-time granularity | 30 |
| `min_advance_minutes` | Minimum lead time before start | 0 |
| `max_advance_days` | Maximum booking window from today | 30 |
| `cancellation_deadline_minutes` | Customer cancel cutoff before start | 60 |
| `customer_can_cancel` | Whether customers may cancel | true |
| `business_can_cancel` | Whether staff/owner may cancel | true |
| `pending_expiry_minutes` | Pending reservation TTL | 30 |
| `check_in_early_minutes` | Early check-in window | 15 |
| `no_show_grace_minutes` | Grace before no-show eligibility | 15 |
| `buffer_minutes` | Post-booking turnover buffer | 0 |
| `allow_same_day_reservations` | Allow bookings on current business day | true |
| `max_active_reservations_per_customer` | Active future limit (null = unlimited) | null |
| `max_daily_reservations_per_customer` | Per-calendar-day limit (null = unlimited) | null |
| `require_customer_note` | Require `notes` on booking | false |
| `metadata` | Optional JSON extensions | null |

Defaults live in `config/reservation_rules.php` — not scattered in controllers.

## Resource-level overrides

Optional nullable columns on `resources`:

- `min_duration_minutes`
- `max_duration_minutes`
- `duration_step_minutes`
- `buffer_minutes`

Resolution order: **resource override → business policy → config default**.

## Duration rules

- Duration must be `>= min`, `<= max`, and divisible by `duration_step_minutes`.
- Start time (business-local) must align with `duration_step_minutes` (e.g. step 30 → 18:00 valid, 18:15 invalid).
- Overnight intervals (e.g. 23:30–01:00) use existing `ReservationIntervalResolver` / `TimeInterval`.

## Advance booking

Evaluated in the **business timezone**:

- **Past:** customer-created reservations cannot start in the past.
- **Same-day:** if `allow_same_day_reservations = false`, bookings on the current business calendar day are rejected (minimum advance still applies when enabled).
- **Minimum advance:** start must be at least `min_advance_minutes` after now.
- **Maximum window:** reservation date cannot be more than `max_advance_days` after today (business-local).

## Cancellation

- **Customer:** requires `customer_can_cancel` and current time `< start - cancellation_deadline_minutes` (business-local).
- **Business:** requires `business_can_cancel`; no deadline (existing state machine still applies).
- **System** cancellations bypass business permission checks.

Error codes: `customer_cancellation_not_allowed`, `cancellation_deadline_passed`, `business_cancellation_not_allowed`.

## Customer limits

Active statuses counted: `pending`, `confirmed` (future start only for active limit).

Not counted: `cancelled`, `completed`, `no_show`, `expired`, `rejected`, `checked_in` (for active limit query).

Limit checks run **inside the reservation transaction** with `SELECT … FOR UPDATE` to reduce race conditions.

## Availability integration

`AvailabilityEngine` consumes `ReservationRulesEngine` for:

- **Request level:** advance booking, same-day, past (`matchAdvanceAvailabilityViolation`)
- **Resource level:** duration min/max/step with resource overrides (`matchDurationAvailabilityViolation`)

New `AvailabilityReason` values: `advance_too_soon`, `advance_too_far`, `same_day_disabled`, `invalid_duration`, `invalid_start_time`.

Buffer conflicts remain in `OccupyingReservationProvider` / `hasConflictIncludingBuffer`.

- Policy `buffer_minutes` is snapshotted as `reservations.buffer_minutes_applied`.
- Occupancy uses half-open `[start, end + buffer)` via `occupancy_range` and GiST EXCLUDE.
- `OccupyingReservationProvider` and `ReservationConflictService::hasConflictIncludingBuffer()` include buffer in conflict detection.
- Adjacent bookings without buffer remain allowed (end equals next start).

## No-show / grace

`no_show_grace_minutes` is exposed via effective settings. Existing `CheckInEligibilityService` continues to read `BookingPolicy` for check-in windows.

## Timezone

All business-local rules use `businesses.timezone` (fallback: `config('rezera.default_business_timezone')`).

## Settings changes vs existing reservations

Policy changes apply to **future operations only**. Existing reservations keep their snapshots (`duration_minutes`, `buffer_minutes_applied`, pricing, status).

## API endpoints

### Management (owner/manager)

```
GET  /api/v1/manage/businesses/{business}/reservation-settings
PUT  /api/v1/manage/businesses/{business}/reservation-settings
POST /api/v1/manage/businesses/{business}/reservation-settings/reset
```

Staff: read via `viewManagement`, cannot update (`manageReservationSettings` policy).

### Public (customer-safe)

```
GET /api/v1/businesses/{business}/reservation-rules
```

Returns duration/advance/cancellation/auto-confirm fields only — no internal limits metadata unless product-safe.

## Authorization

- `BusinessPolicy::manageReservationSettings` → owner/manager (via `canManageBusiness`).
- `BusinessPolicy::viewManagement` → any active member (read settings).

## Error codes

| Code | Meaning |
|------|---------|
| `reservation_duration_too_short` | Below minimum duration |
| `reservation_duration_too_long` | Above maximum duration |
| `invalid_reservation_step` | Duration not aligned to step |
| `reservation_too_soon` | Inside minimum advance window |
| `reservation_too_far_in_future` | Beyond max advance days |
| `same_day_reservations_disabled` | Same-day booking blocked |
| `reservation_in_past` | Start in the past |
| `customer_reservation_limit_reached` | Active/daily limit hit |
| `customer_cancellation_not_allowed` | Policy disables customer cancel |
| `cancellation_deadline_passed` | Past cancellation cutoff |
| `business_cancellation_not_allowed` | Policy disables business cancel |
| `reservation_start_time_invalid` | Start time misaligned with step |
| `reservation_note_required` | Note required but missing |

## Integration flows

### Reservation creation

```
StoreReservationRequest
  → CreateReservationAction
      → ReservationRulesEngine::validateCreation (pre-transaction)
      → DB transaction
          → lock resource
          → validateCustomerLimits (with row lock)
          → hasConflictIncludingBuffer
          → AvailabilityEngine
          → PricingEngine / DiscountEngine
          → create reservation (status from confirmation_mode)
```

### Cancellation

```
CancelReservationAction
  → ReservationTransitionService (status eligibility)
  → ReservationRulesEngine (customer deadline / permissions)
  → status → cancelled
```

## Onboarding / readiness

Onboarding step `reservation_settings` remains satisfied when a `booking_policies` row exists (see `BusinessOnboardingService`). Settings are auto-created on business registration.

## Audit

Settings updates are logged via `CreateAuditLogAction` (`reservation_settings.updated`).

## Migration

`2026_09_02_000150_extend_booking_policies_reservation_rules.php` adds new policy columns and resource override columns. Existing businesses retain their current policy values; new columns use DB defaults (`true`/`null` as documented).

## Testing

See `tests/Feature/Api/V1/Reservation/ReservationSettingsTest.php` for API, duration, advance, limits, cancellation, and auto-confirm coverage.
