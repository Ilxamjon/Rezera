# Business Calendar & Schedule Management

Module **#30** — a read and operational schedule layer for business owners, managers, and staff.

This module provides calendar-oriented APIs for the Flutter management app. It **does not** duplicate the Availability Engine, Reservation Engine, or working-hours logic. It composes existing authoritative services.

## Purpose

Enable calendar views:

- **Daily calendar** — per-resource timeline with booked, active, and available blocks
- **Weekly calendar** — seven-day summary with optional detailed blocks
- **Resource schedule** — multi-day schedule for one resource
- **Reservation timeline** — flat chronological list for a date range

Example daily view:

```text
Cyber Arena — September 2

PC #1
10:00 ───── 12:00  BOOKED
14:00 ───── 16:00  BOOKED

PC #2
09:00 ───── 11:00  AVAILABLE
13:00 ───── 15:00  BOOKED

PS5 VIP
12:00 ───── 14:00  ACTIVE
```

## Architecture

| Component | Role |
|-----------|------|
| `BusinessCalendarService` | Orchestrates calendar views |
| `CalendarScheduleBuilder` | Builds per-resource blocks from working hours + reservations |
| `BusinessHoursResolver` | Open intervals (overnight spillover supported) |
| `ReservationOperationalStatusResolver` | Timeline operational status |
| `ReservationQueryBuilder` overlap logic | Same date-range semantics as booking management |

### What this module does NOT do

- Create or modify reservations (use Reservation Engine / manage reservation APIs)
- Check slot availability for new bookings (use `/availability`)
- Recalculate pricing or conflicts
- Store separate calendar events

## Authorization

Uses `ReservationPolicy::viewBusiness` — any member who can operate bookings (owner, manager, staff) may view the calendar.

## API

Base: `/api/v1/manage/businesses/{business}/calendar`

### Daily calendar

`GET /day`

| Parameter | Description |
|-----------|-------------|
| `date` | `Y-m-d` or `today` (default: today in business timezone) |
| `resource_id` | Filter to one resource |
| `resource_category_id` | Filter to resource group |
| `include_cancelled` | Include cancelled/rejected/expired reservations |
| `hide_available_gaps` | Omit `available` blocks between bookings |

**Response highlights:**

- `resources[]` — each resource with `blocks[]`
- Block `type`: `available`, `booked`, `active`, `completed`, `cancelled`, `closed`, `maintenance`, `inactive`
- `working_hours` — open intervals for the day

### Weekly calendar

`GET /week`

| Parameter | Description |
|-----------|-------------|
| `date` | Any date within the desired week (ISO week, Monday start) |
| `detailed` | When `true`, includes per-resource blocks for each day |
| (same filters as day) | |

**Response:** `days[]` with `summary.reservations_total`, `summary.active_sessions`, `summary.by_status`.

### Reservation timeline

`GET /timeline`

| Parameter | Description |
|-----------|-------------|
| `from` / `to` | Date range (`Y-m-d`), max 31 days. Defaults to next 7 days when omitted. |
| (same filters as day) | |

**Response:** `items[]` with reservation, resource, customer, and `operational_status`.

### Resource schedule

`GET /resources/{resource}`

| Parameter | Description |
|-----------|-------------|
| `from` / `to` | Date range (max 31 days; default 7 days) |
| (same filters as day) | |

**Response:** `days[]` each with `blocks[]` for the resource.

## Block types

| Type | Meaning |
|------|---------|
| `available` | Within working hours, no occupying reservation |
| `booked` | Confirmed/pending future reservation |
| `active` | Checked-in or active session |
| `completed` | Completed reservation |
| `cancelled` | Cancelled/rejected when `include_cancelled=true` |
| `closed` | Outside business working hours |
| `maintenance` | Resource status = maintenance |
| `inactive` | Resource status = inactive |

Reservation blocks include buffer minutes in their interval (consistent with Availability Engine).

## Relationship to other modules

| Module | Relationship |
|--------|--------------|
| Booking Management | Flat reservation lists with filters; calendar adds resource timelines |
| Availability Engine | Point-in-time slot checks; calendar shows existing bookings |
| Dashboard (#29) | Operational counts; calendar shows full schedule |
| Working Hours | Inherited via `BusinessHoursResolver` |

## Configuration

`config/business_calendar.php`:

- `max_range_days` — timeline/resource schedule cap (default 31)
- `default_resource_schedule_days` — default range when `from`/`to` omitted (default 7)
- `week_starts_on` — ISO weekday (default Monday = 1)

## Tests

`tests/Feature/Api/V1/Calendar/BusinessCalendarTest.php`

- Daily blocks (booked, available, active)
- Week summary (7 days)
- Timeline ordering
- Resource multi-day schedule
- Staff access, customer forbidden, cross-business blocked

## Future extensions

- Drag-and-drop reschedule (would delegate to Reservation Engine)
- Staff assignment overlays
- Maintenance window blocks (timed unavailability)
- iCal export
- Real-time WebSocket block updates
