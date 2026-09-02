# Rezera — Product Requirements Document & MVP Blueprint

**Status:** Source of truth for all subsequent development prompts  
**Date:** 2026-09-01  
**Product:** Rezera  
**Market:** Uzbekistan (mobile-first booking and reservation platform)  
**This document does not include application code, migrations, API specs, or UI widgets.**

---

## 1. Executive Summary

Rezera is a mobile-first marketplace that lets customers discover nearby businesses and reserve a specific resource for a specific time window, while giving venue operators a structured way to manage those reservations.

The first launch is optimized for **gaming and computer clubs in Uzbekistan**, because that is the sharpest version of the core problem: customers currently cannot see which PC is free, at what price, and at what time, without calling, messaging, or walking in. The product hypothesis for MVP is:

> Customers will use a mobile app to discover and reserve a specific gaming-club resource before arriving, and club owners will manage those reservations digitally instead of by phone, Telegram, paper, or memory.

The system is **not** a gaming-club app with hardcoded PCs. It is a generic **Business → Resource Group → Resource → Reservation** platform. A computer, a PlayStation room, a restaurant table, a tennis court, and a coworking desk are the same domain object with different type, metadata, and pricing.

MVP is a **single Flutter application** with capability-based access (customer by default; owner/staff via business membership). Backend is a **Laravel modular monolith + PostgreSQL REST API**. Platform administration for MVP is a **protected Laravel admin interface** (Filament recommended), not a second mobile app. Online payments are **out of scope**; pay-at-venue is the commercial model.

The non-negotiable technical constraint is **hard prevention of overlapping bookings** on the same resource, enforced in PostgreSQL (exclusion constraint on a time range) and in Laravel (transaction + row lock + idempotency). Application-only “check if a booking exists” is insufficient.

---

## 2. Product Vision

Rezera becomes the default way to reserve time-based resources in Uzbekistan: find a place, see what is free, lock a slot, show up.

Near-term vision (12–18 months):

- Gaming clubs, PlayStation clubs, and esports centers are fully operational on the platform.
- Restaurants, cafes, coworking, and sports facilities can be onboarded without a rewrite.
- Owners run day-of operations (incoming, active, check-in, no-show) from the same mobile app.
- A web dashboard can be added later against the same API.

Long-term vision (not MVP):

- Deposits and local payments
- Reviews, favorites, loyalty
- Multi-branch chains
- Staff scheduling
- Peak/off-peak and promotional pricing
- Maps-first discovery
- Public inventory widgets / partner integrations

Design principle: **smallest useful reservation product**, architected so category expansion is configuration and metadata, not a new product.

---

## 3. Problem Statement

### 3.1 Customer problems

In Uzbekistan, especially for gaming clubs, customers typically:

- Do not know which venues have free computers right now
- Do not know which specific PC/room is free
- Do not know prices until they ask
- Do not know if the venue is open
- Cannot reliably reserve a slot before traveling
- Coordinate via phone, Telegram, Instagram, or walking in

Friction is high; no-shows and wasted trips are common; popular evening hours are chaotic.

### 3.2 Business problems

Owners typically manage demand via calls, chats, paper, memory, or informal spreadsheets. That causes:

- Double booking of the same PC/table/room
- Lost or forgotten reservations
- Poor utilization (empty PCs while customers think the club is full)
- No occupancy history
- Weak ability to run more than a handful of resources

### 3.3 Why a generic platform (not a “PC club app”)

The reservation problem is identical across categories: **a bookable unit, a time interval, a price, a status, a human on both sides.** Building a PC-only schema would force a rewrite when restaurants or courts are added. Category-specific details (GPU, seating, indoor/outdoor) belong in typed core fields plus constrained metadata, not in the reservation engine.

### 3.4 What MVP must prove

1. A customer can find a club, pick a resource, pick a time, and receive a confirmed (or pending) booking.
2. Two customers cannot hold the same resource for overlapping times.
3. An owner can list resources, see bookings, confirm/reject if required, check a customer in, and cancel according to rules.
4. A small set of real clubs in one city will operate on this instead of Telegram.

If those four fail, extra features will not save the product.

---

## 4. Target Users and Personas

### 4.1 Primary market

- **Country:** Uzbekistan  
- **Launch:** One city first (assumed **Tashkent**; see Assumptions)  
- **First vertical:** Computer / gaming clubs (PC clubs, some PlayStation rooms as the same resource model)  
- **Language at launch:** Russian UI first, with i18n keys for Uzbek and English from day one of Flutter work (Russian is the practical lingua franca for this vertical in Tashkent).

### 4.2 Persona: Customer — “Evening gamer”

- Age 16–28, smartphone-first, Telegram-native
- Goes to PC clubs after school/work, especially 18:00–24:00
- Cares about: free PC now or later tonight, GPU/monitor, price per hour, not traveling to a full club
- Low patience for long onboarding, cards, or complex calendars
- Will cancel if plans change; expects a clear rule

### 4.3 Persona: Customer — “Group booking” (light MVP)

- Wants 2–5 adjacent PCs or a room
- **MVP:** book one resource at a time, repeat if needed. Multi-resource cart is Should Have, not Must Have.

### 4.4 Persona: Owner — “Club administrator”

- Owns or manages 10–80 PCs, maybe VIP zone + regular zone
- Currently answers the phone / Telegram while also handling the floor
- Needs: incoming list, who is on which PC, who is late, disable a broken PC, change hourly price
- Not a software person; will not use a complex desktop ERP in MVP
- May also be a customer of other venues on the same account

### 4.5 Persona: Staff — “Shift operator” (schema now, UI later)

- Checks people in, marks no-show, sees tonight’s board
- **MVP mobile:** owner and manager can do this; dedicated staff invite is Should Have. Data model includes `business_staff` from day one.

### 4.6 Persona: Platform admin — “Rezera operator”

- Approves venues, suspends abuse, views bookings if support needed
- Uses Laravel admin, not Flutter, in MVP

---

## 5. User Roles and Permissions

### 5.1 Role model (recommended)

Do **not** treat “customer vs owner” as mutually exclusive account types.

| Layer | Mechanism | Purpose |
|---|---|---|
| Platform role | `users.platform_role`: `user` \| `platform_admin` | Global admin vs everyone else |
| Customer capability | Implicit: every authenticated `user` is a customer | Browse, book, own bookings |
| Business membership | `business_members` (user, business, member_role) | Owner/manager/staff of a venue |

**Member roles per business:**

| member_role | Meaning |
|---|---|
| `owner` | Full control of that business; can transfer later (future) |
| `manager` | Same operational powers as owner except destructive business delete / ownership transfer |
| `staff` | Floor operations: view bookings, confirm/reject if allowed, check-in, no-show, cancel per policy; cannot edit business profile, hours, prices, or resources |

**MVP simplification:** Flutter exposes owner/manager tools. Creating a business makes the creator `owner`. Inviting staff is Should Have; schema still has `staff`.

A user may:

- Be only a customer
- Own or manage one or many businesses
- Book at other businesses as a customer while in “customer mode”

### 5.2 Who can do what (MVP)

| Action | Customer (self) | Owner / Manager | Staff | Platform admin |
|---|---|---|---|---|
| Register / login | Yes | Yes | Yes | Yes |
| Browse approved businesses | Yes | Yes | Yes | Yes |
| Create reservation | Yes (as customer) | Yes (as customer at other venues; optional self-book at own venue — allowed) | Same | Yes (support — Could Have) |
| View own reservations | Yes | Yes | Yes | Yes |
| Cancel own reservation | Yes, if policy allows | Yes | Yes | Yes |
| Create business | Yes (becomes owner; status `pending_review`) | — | — | Can create/approve |
| Edit business profile, hours, resources, prices, booking config | No | Yes | No | Yes |
| View that business’s bookings | No | Yes | Yes | Yes |
| Confirm / reject pending | No | Yes | Yes | Yes |
| Check-in / complete / no-show | No | Yes | Yes | Yes |
| Cancel any booking of that business | No | Yes | Yes (policy) | Yes |
| Approve / reject / suspend business | No | No | No | Yes |
| Manage categories, users, platform settings | No | No | No | Yes |

### 5.3 Business visibility

- `draft` — owner editing, not submitted (optional; can skip and go straight to pending_review)
- `pending_review` — submitted, not publicly listed
- `approved` — publicly discoverable
- `rejected` — not listed; owner can edit and resubmit
- `suspended` — hidden; existing future bookings handled per policy (see Edge Cases)
- `archived` — permanently closed

**MVP public discovery shows only `approved` and not suspended.**

### 5.4 Authorization implementation (backend)

- Laravel Policies on Business, Resource, Reservation
- Membership lookup: user is member of business with sufficient role
- Platform admin bypass via Gate
- Never trust client-sent “I am owner”

---

## 6. Core Product Architecture

### 6.1 System shape

```
Flutter app (one binary)
    Customer mode  |  Owner mode (if memberships exist)
           \              /
            \            /
         REST API  /api/v1
                  |
         Laravel modular monolith
                  |
         PostgreSQL (source of truth)
                  |
    Laravel admin (Filament)  —  platform admin MVP
                  |
    Queue worker (Redis) — emails/push later, expiry jobs now
```

No microservices. No separate booking service. No Kafka. One deployable API, one database.

### 6.2 Domain boundaries (logical, not separate deploys)

1. **Identity** — users, auth tokens, platform roles  
2. **Catalog** — categories, businesses, locations, hours, members  
3. **Inventory** — resource groups, resources, resource status  
4. **Booking** — availability queries, reservation lifecycle, occupancy  
5. **Pricing** — rate on resource, snapshot on reservation  
6. **Notifications** — notification records; FCM later  
7. **Admin** — approval, suspension, categories  

Payments module exists only as **fields on reservation** in MVP (`payment_status`, `payment_method`), not as a payment engine.

### 6.3 Client architecture principle

One Flutter app, **feature-first**, Riverpod, REST, secure token storage. Owner screens are a feature module, not a second app. Backend APIs are role-agnostic resources with authorization, so a future web dashboard can reuse them.

### 6.4 Time and locale

- Store all instants in **UTC** (`timestamptz`)
- Each business has `timezone` (MVP default `Asia/Tashkent`)
- UI displays local business time
- Money: integer **UZS**, no floats
- Distance: meters internally; UI in km

---

## 7. MVP Scope

### Must Have (customer)

- Phone-based registration and login (phone + password)
- Basic profile (display name, phone)
- List nearby/approved businesses (list, not map-first)
- Search by name
- Filter by category (at least Gaming Club vs others if seeded)
- Business details (name, category, address, hours, description, photos: at least one cover image URL)
- Resource list for a business (name, group/zone, status, hourly price, key metadata e.g. GPU)
- Pick date, start time, duration
- Availability for that resource (and a simple “available in this slot” filter)
- Create booking with server-side validation
- Booking confirmation screen
- Active / upcoming bookings
- Booking history
- Cancel own booking when policy allows
- In-app booking status (no FCM required)

### Must Have (owner)

- Become owner by creating a business (same account)
- Submit business for approval
- Edit business info, address, geo coordinates (manual lat/lng), hours
- CRUD resources (name, group, hourly price, status, metadata)
- Disable resource (maintenance / unavailable)
- Booking config: instant vs manual confirmation; min/max duration; cancellation deadline
- Incoming / upcoming / history bookings for the selected business
- Confirm or reject if manual mode
- Check-in
- Mark no-show
- Cancel booking
- Simple today occupancy: count of active vs total bookable resources (not charts)

### Must Have (platform)

- Admin can approve / reject / suspend businesses
- Admin can view users and bookings (read)
- Seeded categories

### Must Have (platform integrity)

- Overlap exclusion in PostgreSQL
- Booking create in a transaction with resource lock
- Idempotency key on booking create
- Price snapshot on reservation
- Job to expire stale `pending` reservations

### Should Have (shortly after launch)

- SMS or OTP login (Eskiz / Playmobile / Firebase Auth)
- Push via FCM (booking confirmed, rejected, reminder)
- Staff invite
- Multi-resource booking in one checkout
- Map view / sort by distance using device GPS
- Favorites
- Cover gallery (multiple photos)
- Buffer minutes between bookings
- `min_advance` / `max_advance` windows
- Owner basic stats: bookings today, occupancy %, cancellations (7 days)
- Email fallback notifications
- Resource copy / bulk create (PC 01–20)

### Could Have

- Reviews and ratings
- Chat in-app
- Promos / peak pricing
- Deposits and pay-online
- Waitlist
- Recurring bookings
- QR check-in
- Adjacent-PC suggestions
- Public web booking pages
- Owner web dashboard
- Multi-language UI fully translated (Uzbek + English)
- Social login

### Out of Scope (MVP)

- Online payment processing, payment links, billing subscriptions
- Microservices, Kubernetes-required architecture, multi-region
- Real-time WebSocket occupancy (polling / pull-to-refresh is enough)
- Hardcoded PC-only schema
- Separate customer and owner apps
- Complex CRM, inventory of snacks, HR, payroll
- Dynamic surge pricing
- RTL
- Guest checkout without account (identity needed for abuse control)
- Marketplace ads
- Telegram mini-app as primary client (may be a later channel)

---

## 8. Customer User Journey

### Flow 1: Registration and onboarding

- **Entry:** First launch / “Create account”
- **Steps:** Enter phone (Uzbekistan format), password, display name → accept terms → submit → receive tokens → land on Home. Optional: skip extra onboarding; do not force location permission on first screen. Request location when user taps “Nearby”.
- **Backend:** `POST /api/v1/auth/register`, then profile.
- **Success:** Authenticated Home with approved businesses (or empty city state).
- **Error:** Duplicate phone, weak password, validation, network.
- **Empty:** N/A.

### Flow 2: Browse businesses

- **Entry:** Home tab
- **Steps:** Fetch paginated approved businesses; show cover, name, category, area/address, starting price if available, open/closed now (derived from hours + timezone).
- **Backend:** `GET /api/v1/businesses`
- **Success:** Scrollable list
- **Error:** Retry banner
- **Empty:** “No venues in your city yet” + pull to refresh

### Flow 3: Search business

- **Entry:** Search icon / search tab
- **Steps:** Debounced query `q`; optional category chip
- **Backend:** `GET /api/v1/businesses?q=&category_id=`
- **Success:** Rank by name match then city
- **Error / empty:** Clear “no results” not a blank screen

### Flow 4: Open business details

- **Entry:** Tap business
- **Steps:** Header, hours, description, zones/resources preview, “Book” CTA
- **Backend:** `GET /api/v1/businesses/{id}` including hours and resource summary
- **Success:** Details rendered
- **Error:** 404 if unapproved/suspended for customers
- **Empty:** Business with zero bookable resources: “Nothing to book yet”

### Flow 5: Select resource

- **Entry:** Resource list on business page
- **Steps:** Group by resource group (zone). Each row: name, price/hour, status chip, 1–2 spec chips from metadata
- **Backend:** `GET /api/v1/businesses/{id}/resources`
- **Success:** Selection highlights; continue to time
- **Error:** Load failure
- **Empty:** Owner has no active resources

### Flow 6: Check availability

- **Entry:** After resource (or “any available” — MVP: specific resource first)
- **Steps:** Select date (default today in business TZ) → load occupied intervals → pick start from valid slots → pick duration (steps of 30 or 60 min per config)
- **Backend:** `GET /api/v1/resources/{id}/availability?date=`
- **Success:** Slot grid; disabled slots for overlap, outside hours, in the past, resource disabled
- **Error:** Resource became unavailable
- **Empty:** Closed that day; show next open day if cheap to compute, else “Closed this day”

### Flow 7: Create booking

- **Entry:** Review screen (resource, date, start–end, duration, total UZS, confirmation mode, cancellation rule, pay at venue)
- **Steps:** Confirm → API with `Idempotency-Key` → show success
- **Backend:** `POST /api/v1/reservations`
- **Success:** Status `confirmed` (instant) or `pending` (manual); appears in Bookings
- **Error:** 409 overlap, 422 outside hours/rules, 401, timeout with “check Bookings before retrying”
- **Empty:** N/A

### Flow 8: View active booking

- **Entry:** Bookings tab, Upcoming
- **Steps:** Status, address, resource name, time, total, cancel if eligible, directions later (out of scope: open maps URL Should Have)
- **Backend:** `GET /api/v1/reservations/{id}`
- **Success:** Detail
- **Error / empty:** Empty upcoming: “No upcoming bookings”

### Flow 9: Cancel booking

- **Entry:** Detail → Cancel
- **Steps:** Confirm dialog with policy text; optional reason
- **Backend:** `POST /api/v1/reservations/{id}/cancel`
- **Success:** Status `cancelled`; slot released
- **Error:** Past deadline, already checked in, already cancelled
- **Empty:** N/A

### Flow 10: View booking history

- **Entry:** Bookings → History
- **Steps:** Paginated past/cancelled/completed/no_show
- **Backend:** `GET /api/v1/reservations?scope=history`
- **Success:** List
- **Error:** Retry
- **Empty:** “No past bookings”

---

## 9. Business Owner User Journey

### Flow 1: Become a business owner

- **Entry:** Profile → “Add your business”
- **Steps:** No second account. Enter business name, category, city, address → create → user gets `owner` membership → status `pending_review` (or draft then submit)
- **Backend:** `POST /api/v1/owner/businesses`
- **Success:** Owner mode unlocks; switcher appears
- **Error:** Validation
- **Empty:** N/A

### Flow 2: Create business (complete profile)

- **Entry:** Owner dashboard incomplete checklist
- **Steps:** Description, coordinates, cover image, hours, booking config, submit if draft
- **Backend:** PATCH business, PUT hours, POST submit
- **Success:** Waiting for approval banner
- **Error:** Missing required fields
- **Empty:** Checklist of remaining items

### Flow 3: Add resources

- **Entry:** Resources tab
- **Steps:** Optional create group (e.g. “VIP”, “Hall A”) → add resource name, type, hourly price, metadata, status `active`
- **Backend:** resource-groups and resources CRUD
- **Success:** Listed
- **Error:** Duplicate name warning (not hard unique globally; unique per business recommended)
- **Empty:** CTA “Add your first PC / resource”

### Flow 4: Configure prices

- **Entry:** Resource edit
- **Steps:** Set `hourly_rate_uzs` integer; future price changes do not alter existing reservations
- **Backend:** PATCH resource
- **Success:** New bookings use new rate
- **Error:** Non-integer / negative

### Flow 5: Configure working hours

- **Entry:** Business settings
- **Steps:** Per weekday open/close or closed; overnight (e.g. 12:00–06:00) must be supported for PC clubs
- **Backend:** replace hours set
- **Success:** Availability uses hours
- **Error:** Invalid ranges

### Flow 6: Receive booking

- **Entry:** Push later; MVP pull-to-refresh / open Bookings
- **Steps:** New row in Incoming (pending) or Upcoming (confirmed)
- **Backend:** `GET /api/v1/owner/businesses/{id}/reservations`
- **Success:** Visible within refresh
- **Empty:** “No bookings yet”

### Flow 7: Confirm / reject (manual mode)

- **Entry:** Pending item
- **Steps:** Confirm or Reject + optional reason; timeout auto-expires pending (config, default 30 minutes)
- **Backend:** confirm / reject endpoints
- **Success:** Customer sees new status
- **Error:** Already expired; overlap if something else confirmed (should be rare because pending already occupies)

### Flow 8: Manage active reservations

- **Entry:** Dashboard / Bookings → Now
- **Steps:** Filter now / upcoming / today; resource column; status actions
- **Success:** Floor-usable list (not a Gantt in MVP)
- **Empty:** All free

### Flow 9: Check customer in

- **Entry:** Confirmed booking near start (allow from N minutes before — default 15 — until end)
- **Steps:** Check in → status `checked_in`
- **Backend:** `POST .../check-in`
- **Success:** Occupancy reflects in-use
- **Error:** Wrong status, too early/late

### Flow 10: View basic booking statistics

- **Entry:** Dashboard header
- **Steps:** Today: bookings count, currently occupied / total active resources, pending count
- **Backend:** `GET /api/v1/owner/businesses/{id}/stats/today`
- **Success:** Three numbers
- **Empty:** Zeros, not errors

---

## 10. Information Architecture and Navigation

### 10.1 Recommended model: **hybrid (Option C)**

Not two apps. Not a permanently different home solely by registration type.

- Every user is a customer.
- If `business_members` is non-empty, Profile (and a compact control) can switch **App mode: Customer | Business**.
- Mode is a local UI state (persisted on device), not a separate identity.
- Owner of multiple businesses: **business switcher** inside Business mode (selected `business_id` persisted).

**Why not Option A only:** A user who owns a club still needs to book elsewhere. Dual homes without a switcher forces duplicate accounts.

**Why not Option B as a vague “role switch” in settings only:** Day-of operators need a dedicated operational shell (dashboard, floor list). Burying that under customer Home fails the owner persona.

### 10.2 Customer mode tabs (4)

1. **Home** — curated/nearby list, category chips, open/closed  
2. **Search** — query + filters  
3. **Bookings** — Upcoming | History  
4. **Profile** — account, become/add business, language, mode switch if eligible, legal  

Resource/availability/review are **stacks** on Home/Search, not tabs.

### 10.3 Business mode tabs (4)

1. **Dashboard** — today stats, pending count, “now occupying”, incomplete setup checklist  
2. **Bookings** — Pending | Now | Upcoming | History  
3. **Resources** — groups and resources, status toggles  
4. **Business** — profile, hours, booking rules, photos, switch business, “Use as customer”  

### 10.4 Mode transitions

| Event | Behavior |
|---|---|
| Customer creates first business | Membership created; prompt to switch to Business mode; customer tabs remain available |
| Multiple businesses | Switcher on Dashboard and Business tab; all owner APIs scoped to selected business |
| Owner wants to book as customer | Switch to Customer mode; same account; can even book own venue (allowed; owner should still go through occupancy rules) |
| Staff later | Same Business mode, fewer settings |

### 10.5 Unauthenticated

- Can browse businesses and details (read-only) to reduce signup friction
- Booking, profile, owner tools require auth
- **Assumption:** Public browse is allowed for growth.

---

## 11. Generic Business and Resource Model

### 11.1 Hierarchy

```
Category (gaming_club, restaurant, coworking, ...)
    └── Business (venue)
            ├── BusinessMember[]
            ├── BusinessHours[]
            ├── BookingPolicy (1:1)
            ├── ResourceGroup[]   (optional zones: VIP, Hall A)
            └── Resource[]        (PC #12, Table 4, Court 1)
                    └── metadata JSONB (typed by category schema)
```

A resource **may** belong to a group. Ungrouped resources are valid (`resource_group_id` nullable).

### 11.2 Resource is generic

Core columns (typed, indexed, validated):

- `business_id`, `resource_group_id?`, `name`, `slug` or `code` (e.g. `PC-12`)
- `resource_type` (string): `pc`, `console`, `room`, `table`, `desk`, `court`, `other`
- `status`: `active` | `inactive` | `maintenance`
- `capacity` (default 1; tables/courts can be >1 — **MVP booking is still the whole resource**, not per-seat)
- `hourly_rate_uzs` (integer, nullable only if resource not bookable)
- `currency` default `UZS`
- `sort_order`
- `metadata` JSONB

`inactive` = hidden from customers. `maintenance` = visible as unavailable or hidden; **recommendation:** visible with “Unavailable” so regulars understand PC 07 is down. Not bookable.

### 11.3 Category-specific attributes: **hybrid**

| Approach | Verdict |
|---|---|
| Only dedicated columns (cpu, gpu, smoking) | Reject: schema explodes per vertical |
| Only EAV attribute tables | Reject for MVP: too heavy to query and validate |
| Only unconstrained JSONB | Weak: cannot validate, messy clients |
| **Hybrid: core columns + JSONB + category JSON Schema** | **Adopt** |

**MVP:** Store `metadata` JSONB. Keep a **versioned JSON Schema per category** in code (Laravel config / PHP class), validate on write. Flutter renders known keys for `gaming_club` (cpu, gpu, ram_gb, monitor_hz, peripherals) and ignores unknown keys. Restaurants later: seats already in `capacity`; metadata `indoor`, `smoking`.

Do **not** create `computers` table.

### 11.4 Resource groups

Useful for gaming clubs (zones) and restaurants (halls). MVP includes groups because Tashkent clubs almost always have VIP vs regular. Keep the model; UI can default one group “Main”.

### 11.5 Availability vs calendar

Working hours live on **business** (and optionally later on group). Resources inherit business hours in MVP. Per-resource hour exceptions are Could Have. Resource-level **blocks** (maintenance windows) can be `status=maintenance` for all-day; timed blocks are Should Have (`resource_unavailability` intervals). For MVP, maintenance is a status flag, not a calendar of blocks.

---

## 12. Reservation and Booking Lifecycle

### 12.1 Reservation record (conceptual)

- customer_id, business_id, resource_id  
- start_at, end_at (timestamptz)  
- duration_minutes (denormalized, must match end-start)  
- occupancy_range `tstzrange` (for exclusion; includes buffer if enabled)  
- status  
- price snapshot: `hourly_rate_uzs`, `total_amount_uzs`, `currency`  
- confirmation_mode snapshot (`instant` | `manual`)  
- notes (customer)  
- cancellation_reason, cancelled_by (user id + role)  
- rejection_reason  
- payment_status (`pay_at_venue`), payment_method (`venue`)  
- idempotency_key (unique)  
- timestamps  

### 12.2 MVP status set (do not implement unused enterprise states)

| Status | Occupies slot? | MVP? |
|---|---|---|
| `pending` | **Yes** | Yes, if business is manual |
| `confirmed` | **Yes** | Yes |
| `checked_in` | **Yes** | Yes |
| `completed` | No (past) | Yes |
| `cancelled` | No | Yes |
| `rejected` | No | Yes (manual) |
| `expired` | No | Yes (pending timeout) |
| `no_show` | No | Yes |

Occupying statuses for the exclusion constraint: `pending`, `confirmed`, `checked_in`.

### 12.3 State machine

```
                    ┌──────────── expire (job)
                    ▼
[create instant] → confirmed
[create manual]  → pending ──confirm──► confirmed ──check_in──► checked_in ──complete──► completed
                      │                    │
                      ├──reject──► rejected │
                      └──cancel──► cancelled │
                                           ├──cancel──► cancelled (policy)
                                           └──no_show──► no_show (after grace)

checked_in ──/──► cancelled   (not allowed; use complete if they leave early)
```

**Auto-complete:** After `end_at`, a job moves `checked_in` → `completed`.  
**Auto no-show:** If still `confirmed` after `start_at + grace` (default 20 minutes) and not checked in, job or owner action → `no_show`. Owner can also mark no-show manually.  
**Instant businesses** never persist `pending`.

### 12.4 Valid transitions (enforce in one domain service)

| From | To | Actor |
|---|---|---|
| (none) | confirmed | system (instant create) |
| (none) | pending | system (manual create) |
| pending | confirmed | owner/manager/staff |
| pending | rejected | owner/manager/staff |
| pending | cancelled | customer or owner side |
| pending | expired | system job |
| confirmed | cancelled | customer (policy) or owner side |
| confirmed | checked_in | owner/manager/staff |
| confirmed | no_show | owner/manager/staff or system |
| checked_in | completed | owner/manager/staff or system job |

Illegal examples: rejected → confirmed; completed → cancelled; expired → confirmed (customer must create a new booking).

### 12.5 Why pending occupies the slot

If pending did not occupy, two pending + one confirm would overlap. Manual mode is a **hold**. Holds expire so inventory is not frozen forever.

---

## 13. Double-Booking Prevention Strategy

Application-level `WHERE NOT overlap` is **not** enough under concurrency. Two PHP workers can both read “free” and both insert.

### 13.1 Recommended design (Laravel + PostgreSQL)

**Layer A — Database (authoritative)**

1. Enable `btree_gist`.  
2. Column `occupancy_range tstzrange NOT NULL` as `[start_at, end_at)` in UTC. If `buffer_minutes` is on, use `[start_at, end_at + buffer)`.  
3. Partial exclusion constraint:

```text
EXCLUDE USING gist (
  resource_id WITH =,
  occupancy_range WITH &&
) WHERE (status IN ('pending', 'confirmed', 'checked_in'))
```

This makes overlapping occupying rows **impossible** at the storage engine, including races, second API instances, and admin mistakes.

**Layer B — Transaction + lock (good UX, fewer constraint violations)**

On `POST /reservations`:

1. Begin transaction.  
2. `SELECT * FROM resources WHERE id = ? FOR UPDATE` (lock the resource row).  
3. Re-validate hours, duration policy, resource active, business approved.  
4. Insert reservation.  
5. Commit.

If two requests hit the same resource, the second waits on the lock, then either inserts a non-overlapping time or fails validation / exclusion.

**Layer C — Idempotency**

Client sends `Idempotency-Key` (UUID) header. Unique index on `reservations.idempotency_key`. Retry after timeout returns the original reservation instead of a second insert. Network failure after success must not create duplicates.

**Layer D — API contract**

- Overlap → `409 Conflict` with stable error code `resource_not_available`  
- Never 500 for expected overlap (catch exclusion violation and map to 409)

### 13.2 Why not only advisory locks / only Redis holds

Redis holds can expire and drift from SQL. Advisory locks do not protect a second write path (Filament admin, future web, jobs). **SQL exclusion is the source of truth.** Redis is optional later for occupancy caching, never for correctness.

### 13.3 Temporary holds

MVP: `pending` + expiry job **is** the hold. Do not add a separate `booking_holds` table unless payments/deposits appear.

### 13.4 Availability read path

Reads do not need `FOR UPDATE`. Query occupying ranges for the date (business TZ converted to UTC window) and subtract from generated slots. Reads can be slightly stale; create path is strict. Flutter should treat 409 as “slot taken, refresh availability”.

### 13.5 Indexes

- GiST on `occupancy_range` (included in EXCLUDE)  
- `(resource_id, start_at)`  
- `(business_id, start_at)`  
- `(customer_id, start_at)`  
- Partial unique idempotency key  

---

## 14. Pricing Model

### 14.1 MVP pricing

- **Time-based hourly rate** on the resource: `hourly_rate_uzs` integer.  
- Duration in minutes, multiple of `duration_step_minutes` (default 60; allow 30 in config).  
- `total_amount_uzs = round(hourly_rate_uzs * duration_minutes / 60)` using integer arithmetic (compute in minor units; UZS has no tiyin in this product — **1 unit = 1 UZS**).  
- Currency `UZS` only in MVP.  
- Pay at venue; no gateway.

Example: 20,000 UZS/hour × 180 minutes = 60,000 UZS.

### 14.2 Snapshot (mandatory)

Reservation stores `hourly_rate_uzs`, `duration_minutes`, `total_amount_uzs`, `currency` at creation. Owner price edits **never** UPDATE historical totals. Display booking details from snapshot, not from current resource rate.

### 14.3 Future-proofing without building it

Do **not** implement weekday/peak/promo engines now.

When needed, add `price_rules` (business_id, resource_id nullable, rule type, time window, amount) and a Pricing service used only at quote-and-create time. Historical rows remain snapshots. Quote endpoint can be added then; MVP can compute price client-side for display but **server must recompute and persist** (never trust client total).

### 14.4 Quote vs create

MVP: no separate quote API required. Review screen uses last known rate; create recalculates; if rate changed, return `409`/`422` `price_changed` with new total and require explicit confirm (or accept new total — **recommendation:** return error with new amount so the user acknowledges). Simple alternative: accept server total always and show it on success. **Decision:** server wins; response includes computed total; client shows success with server amounts. If client sent `expected_total_uzs` and it differs, `422 price_changed`.

---

## 15. Business Configuration Model

`booking_policies` one-to-one with business.

### 15.1 Implement in MVP

| Setting | Default | Why |
|---|---|---|
| `confirmation_mode` | `instant` | Clubs that live on walk-in need instant; some will want manual |
| `min_duration_minutes` | 60 | Standard PC session |
| `max_duration_minutes` | 480 | Cap abuse (8h) |
| `duration_step_minutes` | 60 | Simple picker |
| `cancellation_deadline_minutes` | 60 | Cancel until 1h before start |
| `pending_expiry_minutes` | 30 | Only used if manual |
| `check_in_early_minutes` | 15 | Floor ops |
| `no_show_grace_minutes` | 20 | Auto or manual no-show |

### 15.2 Defer (Should Have)

| Setting | Why later |
|---|---|
| `min_advance_minutes` | Nice anti-spam; 0 is OK for “book the free PC now” |
| `max_advance_days` | Default implicit 14 days in query/UI without extra config row — **actually include a hard-coded 14-day horizon in MVP code**, promote to config later |
| `buffer_minutes` | Real need after first clubs; exclusion range must include it when added |
| Per-resource hours | Rare |

**MVP implicit:** cannot book in the past; cannot book beyond 14 days; must fit in working hours (including overnight).

### 15.3 Overnight hours

If a club is open 12:00–06:00, a booking 23:00–02:00 is valid. Availability generation must split/span midnight in business timezone, then convert to UTC ranges.

---

## 16. Conceptual Database Entity Model

Do not treat this as a migration. Next prompt will specify columns, indexes, and constraints.

| Entity | Purpose | Key relationships | MVP? |
|---|---|---|---|
| **User** | Account; phone unique; password hash; name; platform_role | 1:N memberships, 1:N reservations as customer | Yes |
| **Personal access token** | Sanctum tokens | User | Yes (Sanctum tables) |
| **Category** | Gaming club, restaurant, … slug + name i18n later | 1:N businesses | Yes |
| **Business** | Venue; status; address; lat/lng; timezone; cover_url; city | category, members, resources | Yes |
| **BusinessMember** | Ownership/staff; unique (user, business) | user, business, member_role | Yes |
| **BusinessHours** | Weekly schedule; support overnight | business, weekday | Yes |
| **BookingPolicy** | Confirmation and duration rules | 1:1 business | Yes |
| **ResourceGroup** | Zone/hall | business, 1:N resources | Yes |
| **Resource** | Bookable unit; rate; status; metadata JSONB | business, group, 1:N reservations | Yes |
| **Reservation** | Booking + occupancy_range + snapshots + status | customer, business, resource | Yes |
| **ReservationEvent** (optional) | Audit of status changes | reservation, actor user | **Yes, lightweight** — useful for disputes; simple table `from_status, to_status, actor_id, reason, created_at` |
| **Payment** | Intent/capture records | reservation | **No table in MVP** — fields on reservation only |
| **Notification** | In-app + future FCM payload | user, type, data JSONB, read_at | **Schema Yes, send No** — table for future; MVP may skip writes except if cheap |
| **DeviceToken** | FCM tokens | user | Should Have |
| **Review** | Ratings | user, business | No |
| **Favorite** | Saved businesses | user, business | No |
| **ResourceUnavailability** | Timed maintenance blocks | resource, range | No (use status) |
| **PriceRule** | Peak/weekend | resource/business | No |
| **Idempotency** | Could be column on reservation only | — | Column, not extra entity |

**Branch:** A future `parent_business_id` or `brand_id` can group venues. **MVP: one Business = one location.** No branch entity now.

**Role entity:** Skip a generic RBAC tables explosion. `platform_role` enum + `member_role` enum is enough. Spatie Permission is optional; **recommendation: enums + policies first**, add Spatie only if admin matrix grows.

---

## 17. Backend Architecture Recommendation

### 17.1 Stack

- PHP 8.3+  
- **Laravel latest stable at implementation** (12.x unless 13 is stable in the environment)  
- PostgreSQL 16+  
- Redis for queues/cache  
- Laravel Sanctum (token auth for mobile)  
- Filament for platform admin  
- Laravel API Resources + Form Requests + Policies  

### 17.2 Modular monolith (keep it boring)

Avoid full DDD packages with 15 interfaces per entity.

Recommended `app/` layout:

```
app/
  Http/Controllers/Api/V1/...
  Http/Requests/...
  Http/Resources/...
  Models/
  Policies/
  Services/
    Booking/CreateReservation.php
    Booking/ReservationStateMachine.php
    Availability/SlotGenerator.php
    Pricing/HourlyPricing.php
  Jobs/ExpirePendingReservations.php
  Jobs/CompleteCheckedInReservations.php
  Admin/  (Filament resources)
```

Optional `src/Domain` is **not** required. One developer + Cursor will move faster with fat-enough services only where invariants live (**booking create** and **state transitions**). CRUD businesses/resources can be thin controllers + model.

### 17.3 Modules (logical)

Merge to avoid noise:

| Module | Contents |
|---|---|
| Identity | Auth, users |
| Catalog | Categories, businesses, hours, members, media URLs |
| Inventory | Groups, resources |
| Booking | Availability, reservations, jobs, exclusion mapping |
| Admin | Filament |

Notifications/Payments: stubs and columns, not modules with empty classes.

### 17.4 Queues

Use database or Redis queue for:

- Expire pending  
- Auto complete / no-show  
- Future FCM  

Do not process these inline on HTTP.

### 17.5 Admin

Filament resources: Users, Businesses (approve actions), Categories, Reservations (read-heavy). This satisfies platform admin without a Flutter admin app.

---

## 18. Flutter Architecture Recommendation

### 18.1 Stack

- Flutter stable  
- Riverpod  
- `go_router` (declarative routes, auth/mode redirects)  
- `dio` + interceptors (auth, idempotency, locale)  
- `flutter_secure_storage` for tokens  
- `freezed`/`json_serializable` only if the team already wants it; otherwise simple Dart classes — **preference: json_serializable for API models, avoid 4 layers of mappers**  
- Easy Localization or similar for RU/UZ/EN keys  

### 18.2 Feature-first folders

```
lib/
  app/          router, theme, bootstrap
  core/         network, errors, result type, storage, widgets, formatters (UZS, phone)
  features/
    auth/
    home/
    search/
    business/      details
    resources/     list + metadata chips
    availability/  date/time/duration
    bookings/      customer
    owner/         dashboard, owner bookings, resources, settings
    profile/
```

Per feature (do not over-split):

- `presentation/` screens, widgets, controllers (Riverpod notifiers)
- `data/` api, dto, repository impl
- `domain/` only if a real entity/validator is shared — **availability slot math can live in data or a small domain file**

### 18.3 Layers (practical)

- Presentation: widgets + Riverpod  
- Repository interfaces in feature, one implementation calling API  
- No UseCase class per button  
- Global: `AuthRepository`, `ApiClient`, `SessionController` (user + appMode + selectedBusinessId)

### 18.4 Error handling

Map API errors to `AppFailure` (network, unauthenticated, validation, conflict, unknown). Booking 409 shows a dedicated “slot taken” UI.

### 18.5 Design system

`core/widgets` + ThemeData. Shared buttons, status chips, price text, empty/error/loading. Owner and customer share components; owner is not a neon-gamer skin.

---

## 19. API Architecture Overview

Detailed OpenAPI is a **later prompt**. Here: domains, versioning, conventions.

### 19.1 Versioning

- Prefix: `/api/v1/`  
- Breaking changes → `/api/v2/`  
- Additive fields are non-breaking  

### 19.2 Conventions

- Plural nouns: `/businesses`, `/resources`, `/reservations`  
- Nested when child cannot exist alone: `/businesses/{id}/resources`  
- Actions as POST subresources: `/reservations/{id}/cancel`, `/check-in`, `/confirm`, `/reject`  
- JSON, snake_case  
- Pagination: `?page=&per_page=` + standard Laravel meta  
- Errors: `{ "error": { "code": "resource_not_available", "message": "...", "details": {} } }`  
- Auth: `Authorization: Bearer`  
- `Idempotency-Key` required on reservation create  
- Locale: `Accept-Language`  

### 19.3 Domains (high level)

| Domain | Examples |
|---|---|
| Auth | register, login, logout, me, refresh if using refresh tokens (Sanctum personal tokens: login/logout/me is enough) |
| Users | profile update |
| Categories | index |
| Businesses (public) | index, show |
| Resources (public) | index by business, show, availability |
| Reservations (customer) | store, index, show, cancel |
| Owner | businesses CRUD, hours, policy, resources CRUD, reservations list, confirm/reject, check-in, no-show, stats |
| Admin | via Filament / optional `/api/v1/admin/*` later |
| Notifications | index, mark read (when implemented) |

Do not expose admin-only mutations on public REST until needed; Filament can use the same models.

---

## 20. Security and Non-Functional Requirements

### 20.1 Security

- HTTPS only in production  
- Sanctum tokens in secure storage; no tokens in logs  
- Password: Laravel hashing (bcrypt/argon2)  
- Phone uniqueness; basic rate limit on login/register/booking (Laravel throttle)  
- Form requests on all writes  
- Policies on all owner endpoints; IDOR tests (cannot confirm another club’s booking)  
- Mass assignment protection  
- Do not store full card data ever  
- Image uploads: if MVP uses URL-only (owner pastes URL) skip file upload; if upload, mime/size validation and private/public disk policy  
- Admin panel behind strong passwords + (Should Have) 2FA  

### 20.2 Performance

- Paginate businesses and reservations  
- Availability: one query of occupying ranges per resource-day; generate slots in PHP (or SQL); typical club 80 PCs × day is fine  
- Nearby: for MVP, filter `city` + order by created; lat/lng stored; distance sort Should Have (`earthdistance` or simple haversine)  
- Indexes as in §13  
- N+1: eager load group, category  

### 20.3 Reliability

- Booking create transactional  
- Exclusion constraint  
- Idempotency  
- Jobs for expiry  
- Backups of PostgreSQL (ops, not code)  

### 20.4 Scalability

Modular monolith handles far beyond first-city MVP. Split services only when availability read load dwarfs writes and caching is not enough (likely after many cities and map-heavy traffic). Vertical scale API + Postgres first; cache business list second.

### 20.5 Observability

- Structured Laravel logs: reservation id, resource id, user id on booking errors  
- Sentry (or similar) on API and Flutter  
- ReservationEvent as functional audit  
- Do not build a full observability stack for MVP  

### 20.6 i18n and devices

- Android + iOS from one Flutter codebase  
- Min screen: small Android; do not design only for Pro Max  
- Time formatting in business TZ  

---

## 21. Edge Cases and Risk Analysis

| Risk | MVP solution |
|---|---|
| Two users book the same slot | Exclusion constraint + `FOR UPDATE` + 409 + refresh availability |
| Booking outside hours | Slot generator + server validation; overnight supported |
| Resource disabled after bookings exist | Cannot create new; existing `confirmed` remain; owner should cancel or honor; UI warns owner if disabling with future occupying bookings (block disable or require confirm — **block disable while occupying future bookings, allow maintenance with warning**) |
| Temporary business close | `suspended` or hours closed all week; hide from search; owner/admin cancel futures or leave them — **admin suspend hides listing; existing bookings stay unless admin/owner bulk-cancels (manual in MVP)** |
| Price change | Snapshot; `expected_total_uzs` mismatch → 422 |
| Customer cancel | Policy window; after check-in forbidden |
| Owner reject | Only from pending; releases slot |
| Customer no-show | Grace + owner button + optional job |
| Time zones | UTC storage; business TZ display; devices in Samarkand vs Tashkent still use venue TZ |
| Multiple businesses | `selectedBusinessId`; every owner API requires business scope |
| User is customer and owner | App mode switch; same tokens |
| Maintenance | `status=maintenance`; not occupying constraint needed beyond no new bookings |
| Network fail on create | Idempotency-Key persisted on client per attempt; Bookings refresh |
| Duplicate tap | Same key; unique index |
| App offline after submit | Success may be unknown → Bookings fetch; do not auto-replay with new key |
| Clock skew | Server time only for validation; do not trust client “now” |
| Booking into the past | Reject |
| Manual pending never handled | Expiry job |
| Owner books own PC | Allowed; still constrained |
| Capacity > 1 | Still one resource one interval; no shared table-split in MVP |
| Photos / abuse | Admin reject business |
| Underage | Age gate not required by default; clubs’ own door policy |
| SMS cost | Password auth first |

---

## 22. Recommended MVP Development Roadmap

### Phase 0 — Product and architecture

- **Goal:** Locked source of truth  
- **Deliverables:** This PRD  
- **Deps:** None  
- **DoD:** Stakeholders treat this file as binding  

### Phase 1 — Backend foundation

- **Goal:** Laravel app, env, PostgreSQL, Redis, health, API skeleton `/api/v1`  
- **Deliverables:** Project bootstrap, config, CI empty tests, Filament install  
- **Deps:** Phase 0  
- **DoD:** `GET /api/v1/health` returns ok; Filament login works locally  

### Phase 2 — Database and authentication

- **Goal:** Users, Sanctum, categories seed, btree_gist  
- **Deliverables:** Migrations for identity + categories; register/login/me  
- **Deps:** Phase 1  
- **DoD:** Register/login tests; phone unique; tokens issued  

### Phase 3 — Business catalog

- **Goal:** Businesses, members, hours, policy, admin approve  
- **Deliverables:** Owner create/update; public list/show only approved; Filament approve  
- **Deps:** Phase 2  
- **DoD:** Unapproved hidden; owner can submit; admin can approve  

### Phase 4 — Resources

- **Goal:** Groups, resources, metadata validation for gaming_club  
- **Deliverables:** CRUD owner; public list  
- **Deps:** Phase 3  
- **DoD:** PC with GPU metadata round-trips; maintenance not bookable  

### Phase 5 — Booking engine

- **Goal:** Availability, create, state machine, exclusion, jobs, pricing snapshot  
- **Deliverables:** Core invariant tests (overlap race, hours, cancel policy)  
- **Deps:** Phase 4  
- **DoD:** Concurrent overlap test fails without constraint and passes with it; 409 mapped; idempotency tested  

### Phase 6 — Flutter customer

- **Goal:** Auth, browse, details, book, list/cancel  
- **Deliverables:** Customer IA, design system baseline  
- **Deps:** Phase 5 (can stub earlier but DoD needs live API)  
- **DoD:** End-to-end book and cancel on a device/emulator against API  

### Phase 7 — Flutter owner

- **Goal:** Mode switch, dashboard, resources, bookings, check-in  
- **Deliverables:** Owner IA  
- **Deps:** Phase 6 + owner APIs  
- **DoD:** Same user creates business, adds PC, receives booking, checks in  

### Phase 8 — Notifications (minimal)

- **Goal:** In-app status is enough; optional FCM  
- **Deliverables:** If time: FCM on confirm/reject; else skip to beta  
- **Deps:** Phase 7  
- **DoD:** Either documented skip or working confirm push  

### Phase 9 — Testing and hardening

- **Goal:** Policy tests, throttle, Sentry, seed demo club  
- **DoD:** Critical booking tests green; demo data for onboarding clubs  

### Phase 10 — Beta launch

- **Goal:** One city, few clubs, real customers  
- **Deliverables:** Production deploy, backups, owner training sheet  
- **DoD:** Live bookings without double-book incidents; pay at venue understood  

Phases 6–7 may overlap once Phase 5 API is stable.

---

## 23. Future Expansion Strategy

- **Categories:** New category row + JSON Schema + Flutter metadata renderer; same booking engine  
- **Web owner dashboard:** Consume `/api/v1/owner/*`  
- **Payments:** `payments` table, `payment_status` transitions, hold vs capture; occupancy already correct  
- **Maps:** PostGIS or haversine index; map tab  
- **Reviews:** After completed bookings only  
- **Multi-resource cart:** Multiple reservation rows + `booking_group_id`  
- **Branches:** `brand_id` on businesses  
- **Realtime:** Pusher/Ably later; not required to prove MVP  

---

## 24. Explicit Assumptions

1. Product name is **Rezera** (workspace name).  
2. First city is **Tashkent**.  
3. Launch UI language is **Russian**, with i18n keys for Uzbek and English.  
4. Primary identifier is **phone number** (Uzbekistan), plus password; OTP is Should Have.  
5. **Pay at venue only**; no payment provider in MVP.  
6. **One resource per booking** in MVP.  
7. **Whole-resource booking** even if capacity > 1.  
8. Public **unauthenticated browse** is allowed; booking requires account.  
9. Default confirmation mode is **instant**.  
10. **14-day** booking horizon hardcoded.  
11. Overnight opening hours are in scope (PC clubs).  
12. Platform admin is **Filament**, not Flutter.  
13. Cover image may be a **URL** in MVP to avoid building a media pipeline first.  
14. Distance/map provider (Google vs Yandex vs OSM) is **undecided**; store WGS84 lat/lng.  
15. Min age is **not** enforced in-app.  
16. Legal terms exist as static URLs.  
17. Currency **UZS integers**.  
18. Staff invites are schema-ready, UI Should Have.  
19. Reviews, favorites, chat, waitlist are out of MVP.  
20. One developer + AI can maintain a modular monolith; that is an intentional constraint.

---

## 25. Architecture Decisions Summary

| ID | Decision | Alternatives rejected |
|---|---|---|
| AD-1 | Generic Business / Group / Resource / Reservation | PC-specific tables |
| AD-2 | Single Flutter app, hybrid customer/owner mode | Two apps; registration-time role lock-in |
| AD-3 | Laravel modular monolith + REST `/api/v1` | Microservices, GraphQL |
| AD-4 | PostgreSQL `tstzrange` + GiST EXCLUDE on occupying statuses | App-only overlap checks; Redis holds as source of truth |
| AD-5 | `FOR UPDATE` on resource + transaction on create | Constraint only (worse UX) |
| AD-6 | Idempotency-Key on create | Client-only debounce |
| AD-7 | Pending occupies inventory | Soft holds without occupancy |
| AD-8 | Hybrid metadata JSONB + per-category schema | EAV; per-vertical tables |
| AD-9 | Membership table for multi-business and staff | Single `is_owner` flag on user |
| AD-10 | Hourly integer UZS + snapshot on reservation | Trust client price; floats |
| AD-11 | Pay-at-venue fields only | Stripe/Payme in MVP |
| AD-12 | Sanctum token auth | JWT custom stack unless proven need |
| AD-13 | Filament for platform admin | Flutter admin app |
| AD-14 | Riverpod + feature-first Flutter | Bloc unless existing preference |
| AD-15 | Compact MVP statuses including no_show and expired | Huge CRM status catalogs |
| AD-16 | Instant vs manual as the main policy fork | Many booking modes |
| AD-17 | UTC storage, business timezone display | Store local timestamps |
| AD-18 | No WebSockets in MVP | Live occupancy streaming |

---

## 26. Cursor Development Rules

Future prompts and agents **must** follow:

1. Do not rewrite working functionality unnecessarily.  
2. Do not change the database schema without explicitly explaining impact on occupancy, snapshots, and APIs.  
3. Do not introduce a new package unless necessary; prefer Laravel/Flutter built-ins.  
4. Prefer existing project patterns (folder structure, error envelope, Riverpod, Form Requests).  
5. Keep code production-oriented and readable.  
6. Avoid placeholder implementations unless explicitly requested.  
7. Never silently remove existing functionality.  
8. Run or suggest appropriate tests after major changes — **booking overlap tests are mandatory** if the booking engine changes.  
9. Follow separation of concerns: HTTP thin, invariants in booking services, SQL enforces occupancy.  
10. Avoid unnecessary abstractions (no UseCase per widget, no empty DDD layers).  
11. Keep API contracts consistent (`/api/v1`, snake_case, error codes).  
12. Consider backward compatibility of API fields.  
13. Add comments only for non-obvious decisions (e.g. why pending occupies).  
14. Ask a question only when a safe assumption is impossible.  
15. If information is missing, make the most reasonable assumption, label it, and continue.  
16. **Never** implement category-specific booking tables.  
17. **Never** trust the client for price, overlap, or role.  
18. Money is integer UZS; no floats.  
19. Datetimes are `timestamptz` UTC.  
20. Occupying statuses remain aligned with the exclusion constraint.  
21. Do not start payments, reviews, or microservices unless a prompt explicitly expands scope.  
22. Filament admin must not bypass occupancy rules (same tables/constraints).  
23. Flutter owner and customer share auth session; do not split token types per mode.

---

## 27. Final MVP Definition of Done

The MVP is done when all of the following are true:

1. A new user can register with phone and password and browse approved Tashkent (or configured city) businesses.  
2. An approved gaming club appears with zones/resources, specs, hourly prices, and hours (including overnight if configured).  
3. A customer can select a resource, date, time, and duration, see a server-calculated UZS total, and create a booking.  
4. Instant clubs land on `confirmed`; manual clubs land on `pending` and occupy the slot until confirm, reject, cancel, or expiry.  
5. Concurrent attempts to book the same resource and overlapping interval yield **exactly one** occupying reservation; the other receives a conflict error.  
6. The customer can view upcoming/history and cancel within policy.  
7. The same user can open Business mode, manage resources and hours, see bookings, confirm/reject if manual, check in, and mark no-show.  
8. Platform admin can approve/reject/suspend businesses in Laravel admin.  
9. Historical booking prices do not change when the owner edits rates.  
10. Pay-at-venue is the only payment path and is communicated in the UI.  
11. No PC-specific schema exists; gaming fields live in metadata.  
12. Automated tests cover overlap, hours violation, cancel policy, and unauthorized owner access.  
13. The app is usable on a common Android phone in Russian, without looking like a neon-only gaming title at the shell level.

---

## NEXT DEVELOPMENT PROMPT CONTEXT

The next Cursor prompt must **inherit** these decisions and produce the **PostgreSQL conceptual-to-physical schema and Laravel migrations** (still no full app features beyond what migrations need):

- Product: **Rezera** — generic venue booking, gaming-club-first content, not PC-hardcoded schema.  
- Stack: Laravel modular monolith, PostgreSQL, Sanctum later, Filament later.  
- Core entities: User, Category, Business, BusinessMember, BusinessHours, BookingPolicy, ResourceGroup, Resource, Reservation, ReservationEvent.  
- Resource.metadata JSONB + application-level category schema; `resource_type` string.  
- Reservation stores `tstzrange occupancy_range`, integer UZS snapshots, `idempotency_key`.  
- Exclusion constraint on `(resource_id, occupancy_range)` WHERE status IN (`pending`,`confirmed`,`checked_in`); requires `btree_gist`.  
- Status enum as in §12; pending occupies.  
- Membership roles `owner|manager|staff`; `users.platform_role` `user|platform_admin`.  
- Business statuses include `pending_review`, `approved`, `rejected`, `suspended`.  
- No payments table; `payment_status`/`payment_method` on reservations.  
- No reviews/favorites tables.  
- Time: `timestamptz` UTC + `businesses.timezone`.  
- Do not write Flutter or controllers in the next step unless that prompt asks; schema and integrity first.  
- Follow §26 Cursor rules.  
- Read this file `docs/PRD-MVP-BLUEPRINT.md` as the source of truth.

---

*End of PRD / MVP Blueprint.*
