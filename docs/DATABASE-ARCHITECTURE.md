# Rezera — PostgreSQL Database Architecture, ERD & Schema Design

**Status:** Source of truth for schema and migrations (Prompt #2)  
**Implementation:** See `docs/DATABASE_IMPLEMENTATION.md` for applied migrations and models.  
**Depends on:** `docs/PRD-MVP-BLUEPRINT.md`  
**Date:** 2026-09-01  
**This document does not include Laravel migration PHP, Eloquent models, controllers, API specs, or Flutter code.**

---

## 1. Database Architecture Summary

Rezera’s database is a **single PostgreSQL 16+ catalog** behind a Laravel modular monolith. There is one physical venue row per `businesses` record, generic `resources` (never a `computers` table), and `reservations` whose **occupancy is enforced by a partial GiST exclusion constraint** on `tstzrange`.

MVP tables (domain):

| Area | Tables |
|---|---|
| Identity | `users` (+ Laravel Sanctum/password tables) |
| Catalog | `business_categories`, `businesses`, `business_members`, `booking_policies`, `business_hours` |
| Inventory | `resource_groups`, `resources` |
| Booking | `reservations`, `reservation_events` |

Not in MVP: `payments`, `reviews`, `favorites`, `notifications`, `brands`/`branches`, `resource_unavailability`, `price_rules`, PostGIS.

Integrity model: **foreign keys + CHECKs + unique/partial unique + one EXCLUDE**. Application transactions (`FOR UPDATE` on the resource row + idempotency key) reduce user-facing conflicts; **PostgreSQL remains the last line of defense**.

---

## 2. Core Database Design Decisions

| Decision | Choice |
|---|---|
| Engine | PostgreSQL 16+ only (not designed for MySQL) |
| Primary keys | UUID, ordered (v7 / Laravel `HasUuids`) |
| Time | `timestamptz` UTC for instants; `time` + weekday for weekly hours; `businesses.timezone` IANA |
| Money | `bigint` amounts = whole **UZS** (1 unit = 1 soʻm); `char(3)` currency |
| Roles | `users.platform_role` + `business_members.member_role` (not a single user role) |
| Venue vs branch | **Business = one location** (Option A). No `branches` table |
| Metadata | JSONB + CHECK shape light; **validation of PC specs in application JSON Schema** |
| Pricing | `resources.hourly_rate_amount` (Option A); snapshot on reservation |
| Availability | Derived: hours + resource status + occupying reservations. No availability snapshot table |
| Occupancy | `reservations.occupancy_range` generated `tstzrange` + `EXCLUDE USING gist` |
| Locales (dynamic) | JSONB `{uz,kaa,ru}` for **platform** copy (categories). Owner content: single `name`/`description` + optional `translations` JSONB |
| Soft delete | Users, businesses, resources only. Reservations never soft-deleted |
| Audit | `reservation_events` for booking status; approval fields on `businesses` |
| Extensions | `btree_gist` (required), `pgcrypto` only if generating UUIDs in SQL (Laravel can generate in app) |

---

## 3. ID Strategy Decision

**Primary recommendation: UUID stored as PostgreSQL `uuid`, generated as UUID v7 (time-ordered) in Laravel.**

| Option | Verdict |
|---|---|
| `BIGINT IDENTITY` | Fastest and smallest. **Rejected as public PK:** leaks volume, sequential guessing, painful if APIs ever federate. Internal-only bigint + separate public UUID is extra mapping for a one-developer MVP. |
| UUID v4 | Native Laravel historically. Random values **fragment B-tree indexes** on hot tables (`reservations`). |
| ULID (`char(26)`) | Sortable, popular. Slightly worse than native `uuid` (wider index, not a PG type). |
| **UUID v7 / ordered UUID** | **Adopt.** 128-bit, `uuid` type, index locality similar to time-ordered ids, first-class in Laravel 11+ `HasUuids`, opaque in URLs, no extra public_id column. |

**API exposure:** use the UUID as the resource id (`/reservations/{uuid}`). No integer ids in JSON.

**Trade-off accepted:** ~2× wider PKs vs bigint; irrelevant at MVP scale (tens of clubs, thousands of bookings/day). Revisit only at extreme write volume.

---

## 4. Timezone Strategy

**Standard (mandatory):**

1. **All absolute instants** (`start_at`, `end_at`, `checked_in_at`, `created_at`, verification timestamps) are **`timestamptz`**. PostgreSQL stores UTC. Laravel `datetime` casts with `UTC` timezone in `config/app.php` (`APP_TIMEZONE=UTC`).
2. **Each business has `timezone` IANA** (MVP default `Asia/Tashkent`). Uzbekistan has **no DST**; still do not hardcode `+05:00` only — use IANA names for future cities/countries.
3. **Working hours** are **local civil time** (`time` without time zone) + `weekday`, interpreted **in `businesses.timezone`**. Conversion to UTC happens in the booking/availability service when building slots and validating `start_at`/`end_at`.
4. **Never store reservation start as a naive local timestamp.** Never trust the client’s phone timezone for occupancy.
5. **Date-based queries** (“today at this club”): compute the club’s local `[start,end)` of that calendar date, convert to UTC, then filter `start_at`/`occupancy_range`.
6. **Overnight hours:** local `opens_at` 20:00 and `closes_at` 04:00 means the open interval **crosses midnight** in that timezone (see §10). A booking 23:00–02:00 local is two UTC instants still stored on one reservation row.

Flutter displays times converted to the **venue timezone** (and may also show a hint if the user’s zone differs).

---

## 5. Money and Currency Strategy

- **Do not use `float`/`double`/`real`/`numeric` for money in MVP** unless needed for FX later. **`bigint` integer UZS.**
- Column naming: `hourly_rate_amount`, `total_amount` plus `currency char(3)` default `'UZS'`.
- UZS has no practical tiyin in this product: **minor unit = 1 UZS**.
- Reservation stores **`hourly_rate_amount`, `duration_minutes`, `total_amount`, `currency`** at insert time. Owner updates to `resources.hourly_rate_amount` do not cascade.
- Future currencies: keep `currency` on resource and snapshot; do not add FX tables now.
- `CHECK (hourly_rate_amount >= 0 AND total_amount >= 0)`.

---

## 6. User, Role and Authorization Schema

### 6.1 Platform vs venue

| Layer | Storage | Values |
|---|---|---|
| Platform | `users.platform_role` | `user` (default), `platform_admin` |
| Customer | Implicit | Every `user` with status `active` may book |
| Venue | `business_members` | `owner`, `manager`, `staff` |

**Rejected:** a single `users.role` that is `customer` XOR `business_owner` (blocks dual use).  
**Rejected for MVP:** Spatie Permission / roles pivot (overkill; add later if Filament matrix explodes).  
**Rejected:** `roles` + `role_user` only — still would not model per-business staff.

### 6.2 `users`

- **Phone is the primary identifier.** Store **E.164** only, e.g. `+998901234567`.
- Normalize in the application **before write** (strip spaces, convert `998…` / `8…` / `90…` to `+998…`). Unique index on `phone`.
- `email` optional, unique where not null (`UNIQUE` + NULLs allowed multiple times in PostgreSQL).
- `locale`: `uz` | `kaa` | `ru` (UI preference; Flutter still owns static strings).
- `status`: `active` | `blocked`. Soft delete via `deleted_at` (see §28).
- Password: Laravel hash in `password` (`varchar(255)`).

No `customers` table. No separate `owners` table.

---

## 7. Business and Membership Schema

**Option A — business is the venue (adopted).**

- One row = one address, one timezone, one hour grid, one public listing.
- Chains later: add `brands` + nullable `businesses.brand_id`. That is one additive migration, not a rewrite. A premature `branches` table doubles every FK (`resources.branch_id` vs `business_id`) for zero MVP benefit.

**Ownership:** not `businesses.owner_id` as the only link. **`business_members` is canonical.** Also store `businesses.created_by_user_id` (RESTRICT) for who submitted the listing.

**`booking_policies`:** 1:1 with business (`business_id UNIQUE`). Keeps `businesses` from becoming a 40-column settings dump. Always created in the same transaction as the business.

**`businesses.status`:** `draft` | `pending_review` | `approved` | `rejected` | `suspended` | `archived`. Public discovery: `approved` AND `deleted_at IS NULL`.

---

## 8. Business Category Schema

Flat list (no parent categories in MVP).

- `slug` immutable-ish unique (`gaming_club`, `playstation_club`, `restaurant`, `cafe`, `coworking`, `sports_facility`).
- `name jsonb` **required keys** `uz`, `kaa`, `ru` (platform-managed; seed in migration).
- `is_active`, `sort_order`, optional `icon` (key or URL).
- `resource_metadata_schema jsonb` optional: JSON Schema document for that category’s `resources.metadata` (can live in PHP config instead; **DB column is useful so admin can evolve without deploy** — if unused, keep NULL and use code). **Recommendation:** schema in **Laravel config for MVP**; column omitted to avoid two sources of truth. Categories table stays names + slug + flags.

No per-business custom categories in MVP.

---

## 9. Business Location Schema

Columns **on `businesses`** (no `addresses` table, no PostGIS):

| Field | Role |
|---|---|
| `country_code` | `CHAR(2)` default `UZ` |
| `region` | e.g. Toshkent viloyati / Toshkent shahri |
| `city` | e.g. Tashkent, Nukus |
| `district` | optional tuman |
| `address_line` | street text |
| `latitude`, `longitude` | `NUMERIC(9,6)` WGS84, both NULL or both NOT NULL |

**PostGIS:** **not** for MVP. Nearby search: filter `city` + optional bounding box on lat/lng (`CHECK` ranges). Haversine in SQL or app when GPS exists (Should Have).

No `cities` reference table for MVP (free-text + convention). Add a geo directory when multi-city quality requires it.

---

## 10. Business Working Hours Schema

Table **`business_hours`**: exactly **7 rows per business** (one per ISO weekday).

| Column | Meaning |
|---|---|
| `weekday` | `SMALLINT` **1 = Monday … 7 = Sunday** (ISO 8601) |
| `is_closed` | Closed that weekday |
| `is_open_24h` | Open all 24 hours that weekday |
| `opens_at`, `closes_at` | `time` local, nullable |

**CHECKs:**

- If `is_closed`: times NULL, `is_open_24h` false.
- If `is_open_24h`: times NULL, not closed.
- Else: both times NOT NULL.

**Overnight (example 20:00–04:00):** `opens_at = 20:00`, `closes_at = 04:00`, `closes_at < opens_at` (and not 24h). Availability logic treats the open interval as `[opens, 24:00) ∪ [00:00, closes)` on that weekday, and a session may **span midnight** as a single reservation (still one `start_at`/`end_at` in UTC).

**24/7:** all seven days `is_open_24h = true`.

**Not in MVP:** holiday exceptions, one-off closures. **Later:** `business_hour_exceptions` (`date`, closed or override times). Until then, `suspended`/`archived` or set weekdays closed.

Resources **inherit** business hours. No per-resource hours table.

---

## 11. Resource Group and Resource Schema

**Resource groups are in MVP** (zones: VIP, Hall A). `resources.resource_group_id` **nullable** (ungrouped allowed).

**`resource_groups`:** `business_id`, `name`, `sort_order`. Unique `(business_id, name)` among non-deleted.

**`resources`:** generic bookable unit.

| Status | Customer visibility | Bookable |
|---|---|---|
| `active` | Yes | Yes (if business approved) |
| `maintenance` | Yes (“unavailable”) | No |
| `inactive` | No | No |

`resource_type` `text` + CHECK: `pc`, `console`, `room`, `table`, `desk`, `court`, `other`.

`capacity` default 1; **MVP still books the whole resource** (no seat-level split).

Unique `(business_id, code)` where `deleted_at IS NULL` (e.g. `PC-01`).

`ON DELETE` for group: **SET NULL** (resource remains).

---

## 12. Category-Specific Resource Metadata Strategy

**Adopted: Option D hybrid — typed core columns + `metadata JSONB` + application JSON Schema.**

| Option | Why not as primary |
|---|---|
| A Separate spec tables (`computers`, `tables`) | Hardcoded verticals; PRD forbids |
| B JSONB only, unconstrained | Invalid GPU strings, unqueryable chaos |
| C EAV `attributes`/`values` | Heavy joins, weak types, slow to build |
| D Hybrid | Core query fields stay columns; extras in JSONB |

**MVP metadata example (`gaming_club`):**  
`{"cpu":"Ryzen 7 5800X","gpu":"RTX 4070","ram_gb":32,"storage":"1TB NVMe","monitor":"27in","monitor_hz":165,"peripherals":"HyperX"}`

Restaurant later: `capacity` column + `{"indoor":true,"smoking":false}`.

**Query:** list/filter by GPU is **not** an MVP indexed query. If needed later, expression index `(metadata->>'gpu')` or promote a column.

**Laravel:** `$casts = ['metadata' => 'array']`; validate with category schema in Form Request / service. DB: `metadata JSONB NOT NULL DEFAULT '{}'` and optional `CHECK (jsonb_typeof(metadata) = 'object')`.

---

## 13. Resource Pricing Strategy

**Adopted: Option A — `hourly_rate_amount` + `currency` on `resources`.**

| Option | Verdict |
|---|---|
| A Price on resource | Matches MVP (one hourly rate per PC). Simple owner UI |
| B `resource_prices` table | Needed when multiple rules exist; extra joins now |
| C Generic rule engine | Overbuild |

**Escape hatch:** when weekend/peak/promo exists, add `price_rules` (nullable `resource_id`, time windows, amount) and a pricing service used **only at quote/create**. Historical `reservations.total_amount` stays.

`hourly_rate_amount` NOT NULL for `active` resources (enforce in app; DB allows NULL for incomplete drafts — **CHECK:** if status `active` then rate IS NOT NULL). Prefer DB:  
`CHECK (status <> 'active' OR hourly_rate_amount IS NOT NULL)`.

---

## 14. Availability Architecture

**No `availabilities` or `time_slots` table.** Those duplicate hours + reservations and go stale.

A slot is bookable iff all are true:

1. Business `approved` and not deleted/suspended.
2. Resource `active` and not deleted.
3. Local start/end inside working hours (overnight rules applied).
4. Duration matches policy (min/max/step).
5. Within horizon (`booking_policies.max_advance_days`, `min_advance_minutes`).
6. No occupying reservation whose `occupancy_range` overlaps (and EXCLUDE will reject races).

**Unavailability sources (MVP):** resource `inactive`/`maintenance`; business closed that weekday; occupying reservation.

**Later blackouts:** `resource_unavailability (resource_id, range tstzrange)` with its own EXCLUDE or merge into occupancy generation — **do not create now**.

---

## 15. Reservation Schema

One row = one customer + one resource + one interval.

**Stored (not derived only):**

- `customer_id`, `business_id`, `resource_id` (denormalize `business_id` so owner queries skip extra join and so moving a resource between businesses cannot rewrite history — **resources must not change `business_id` after creation**; CHECK/app).
- `start_at`, `end_at` (`end_at > start_at`).
- `duration_minutes` generated from timestamps (or written in same transaction with CHECK equality).
- `occupancy_range tstzrange` generated `[start_at, end_at)` — **buffer:** `booking_policies.buffer_minutes` default 0; when > 0, occupancy must be `[start_at, end_at + buffer)` — **therefore occupancy cannot be a simple generated column if buffer is per-business.**  
  **Decision:** store `occupancy_range` as a **real column** set by the application (or trigger) to `[start_at, end_at + buffer_minutes)`. Include `buffer_minutes` snapshot on the reservation (`buffer_minutes_applied`) so history stays correct if policy changes.
- `status`
- Price snapshot columns
- `confirmation_mode` snapshot (`instant` | `manual`)
- `payment_status` default `pay_at_venue`, `payment_method` default `venue`
- `notes`, `cancellation_reason`, `rejection_reason`
- `cancelled_by_user_id`, `cancelled_by_actor_type`
- `checked_in_at`, `completed_at`
- `idempotency_key` UUID unique
- `expires_at` for `pending` (set at create)

**Who occupies:** see §16–17.

---

## 16. Reservation Status State Machine

### 16.1 MVP statuses (all eight are used)

| Status | Meaning | Blocks resource? |
|---|---|---|
| `pending` | Manual hold; waiting owner | **Yes** |
| `confirmed` | Slot is the customer’s | **Yes** |
| `checked_in` | Customer on site | **Yes** |
| `completed` | Session finished | No |
| `cancelled` | Customer or staff cancelled | No |
| `rejected` | Owner declined pending | No |
| `expired` | Pending timed out | No |
| `no_show` | Did not arrive | No |

Cancelled/rejected/expired/no_show/completed **do not** participate in the EXCLUDE predicate, so they **free the interval** (completed is typically in the past anyway).

### 16.2 Transition table

| Current Status | Action | Next Status | Actor | Blocks Resource? (after) |
|---|---|---|---|---|
| *(none)* | Create (instant policy) | `confirmed` | Customer (system write) | Yes |
| *(none)* | Create (manual policy) | `pending` | Customer (system write) | Yes |
| `pending` | Confirm | `confirmed` | Owner / manager / staff | Yes |
| `pending` | Reject | `rejected` | Owner / manager / staff | No |
| `pending` | Cancel | `cancelled` | Customer or owner side | No |
| `pending` | Timeout job | `expired` | System | No |
| `confirmed` | Cancel (policy) | `cancelled` | Customer or owner side | No |
| `confirmed` | Check-in | `checked_in` | Owner / manager / staff | Yes |
| `confirmed` | No-show | `no_show` | Owner / manager / staff or system | No |
| `checked_in` | Complete | `completed` | Owner / manager / staff or system | No |

**Forbidden:** `checked_in` → `cancelled`; `rejected`/`expired`/`completed` → any occupying status (create a **new** reservation). `pending` → `checked_in` (must confirm first, or instant path never pending).

**History:** **Option B** — current `status` on `reservations` **plus** `reservation_events` (append-only). Justified: disputes, support, Filament. Not a generic audit log of every column.

---

## 17. Double-Booking Prevention Strategy

### 17.1 Occupying predicate

```text
status IN ('pending', 'confirmed', 'checked_in')
```

Cancelled, rejected, expired, completed, no_show → **no overlap blocking**.

Pending **must** occupy: otherwise two pendings plus one confirm double-book.

### 17.2 PostgreSQL exclusion (required)

Extension: **`btree_gist`**.

```sql
ALTER TABLE reservations
  ADD CONSTRAINT reservations_no_overlapping_occupancy
  EXCLUDE USING gist (
    resource_id WITH =,
    occupancy_range WITH &&
  )
  WHERE (status IN ('pending', 'confirmed', 'checked_in')
         AND deleted_at IS NULL);
```

(`deleted_at` is **not** on reservations; omit that conjunct if no such column.)

- `&&` = range overlap (including touching? **`tstzrange` `[start,end)` is half-open:** 18:00–21:00 and 21:00–23:00 **do not overlap**. Adjacent bookings allowed. If `buffer_minutes > 0`, encode buffer **inside** `occupancy_range` so 21:00 + 10 min buffer blocks 21:00–21:10.

### 17.3 Laravel / concurrency

1. Client: `Idempotency-Key` (UUID). Unique index on `reservations.idempotency_key`. Retry returns the same row.
2. `DB::transaction`: `Resource::lockForUpdate()->find($id)` (`SELECT … FOR UPDATE`).
3. Validate hours, policy, resource active, business approved, `expected_total_amount`.
4. Compute `occupancy_range`; `INSERT`.
5. If EXCLUDE raises `23P01 exclusion_violation` → rollback → HTTP 409 `resource_not_available`.

**Simultaneous requests:** second waits on row lock, then either inserts a non-overlapping range or fails validation/EXCLUDE. Two different resources do not block each other.

**Status change to cancelled:** `UPDATE` removes row from partial EXCLUDE; slot frees immediately. No second table to update.

**Laravel Schema Builder:** **cannot** express `EXCLUDE USING gist` portably. Use **`DB::statement`** in a dedicated migration. Doctrine schema dumps may miss it — keep the raw migration as source of truth.

**Do not** use Redis as source of truth. Do not rely on `WHERE NOT overlap` without EXCLUDE.

### 17.4 Generated vs stored range

Application (or BEFORE INSERT/UPDATE trigger) sets:

```text
occupancy_range = tstzrange(start_at, end_at + buffer_minutes_applied * interval '1 minute', '[)')
```

Trigger is optional; **one write path in `CreateReservation`** is enough if Filament uses the same service. **Filament must not raw-insert overlapping rows** — use the service or the EXCLUDE will still reject.

---

## 18. PostgreSQL Constraints and Integrity Rules

| Rule | Mechanism |
|---|---|
| No orphan reservations | FKs: customer, business, resource `RESTRICT` |
| Resource belongs to same business as reservation | **Trigger or CHECK via composite FK** `(resource_id, business_id)` referencing `resources (id, business_id)` |
| One occupancy per overlapping interval | EXCLUDE §17 |
| Unique phone | `UNIQUE (phone)` |
| One membership per user per business | `UNIQUE (business_id, user_id)` |
| One policy per business | `UNIQUE (booking_policies.business_id)` |
| Seven hours rows | Unique `(business_id, weekday)` + app seed |
| Enum-like columns | `TEXT` + `CHECK (… IN (…))` (easier to extend than PG ENUM) |
| `end_at > start_at` | CHECK |
| Lat/lng pair | CHECK both null or both not null; ranges ±90/±180 |
| Active resource has price | CHECK |
| Idempotent creates | `UNIQUE (idempotency_key)` |

**ON DELETE (summary):**

| Parent → child | Behavior |
|---|---|
| users → reservations.customer_id | **RESTRICT** (anonymize/soft-delete user instead) |
| users → business_members | **RESTRICT** if owner; or revoke membership first |
| businesses → resources | **RESTRICT** if any resource; archive business instead |
| businesses → reservations | **RESTRICT** |
| resources → reservations | **RESTRICT** |
| businesses → business_hours / booking_policies / members / groups | **CASCADE** (dependent config) |
| resource_groups → resources.group | **SET NULL** |
| reservations → reservation_events | **CASCADE** |
| businesses.category_id | **RESTRICT** |
| businesses.created_by_user_id | **RESTRICT** |
| reservations.cancelled_by_user_id | **SET NULL** |

---

## 19. Multilingual Data Strategy

**UI (Flutter):** ARB/JSON for Uzbek (`uz`), Karakalpak (`kaa`), Russian (`ru`). **Not in PostgreSQL.**

**Dynamic content:**

| Content | Strategy |
|---|---|
| Category names | **JSONB** `{"uz":"…","kaa":"…","ru":"…"}` — **Approach B**. ~10 rows; query `name->>'ru'`. Adding `en` is a data update, not 3 new columns |
| Category as Approach A (`name_uz`…) | Rejected: schema change per language |
| Translation table | Rejected for MVP (joins for every list) |
| Business name/description | **Single required `name` + optional `description`** (owner’s working language). Optional `translations jsonb` `{uz:{name,description},kaa:{},ru:{}}` for later; **not required to publish** |
| Resource name/code | Not translated (`PC-01`) |
| Cancellation policy prose | Flutter + numeric policy fields, not CMS |
| User locale | `users.locale` |

**Validation:** categories seed must include all three keys. API returns `name` object; Flutter picks `locale` with fallback `ru` then `uz`.

---

## 20. Payments Database Preparation

**No `payments` table in MVP.**

On `reservations`:

- `payment_status` `TEXT` default `unpaid` with CHECK `unpaid` | `pay_at_venue` | `paid` | `refunded` | `void`  
  MVP writes **`pay_at_venue`** at create (or `unpaid` then treat as pay at venue — **decision: `pay_at_venue`**).
- `payment_method` default `venue`

**Later:** `payments` (provider, amount, status, reservation_id, provider_ref). Occupancy does not depend on payment until deposits exist (then pending occupancy already holds).

---

## 21. Notifications Database Preparation

**No `notifications` domain table in MVP.**

Use Laravel’s notification system when FCM ships; optional `notifications` table from `php artisan notifications:table` at that time. Optional later: `device_tokens (user_id, token, platform)`.

Booking status in the app is **pull from `reservations`**. Do not duplicate event buses in SQL now.

---

## 22. Reviews and Favorites Decision

| Feature | Decision |
|---|---|
| Reviews | **Deferred.** No tables. Add `reviews` after `completed` bookings exist in production |
| Favorites | **Deferred.** Simple `user_id + business_id` unique later; not worth a migration in MVP |

---

## 23. Audit and History Strategy

| Area | Approach |
|---|---|
| Reservation status | **`reservation_events`** required |
| Business approval | Columns: `submitted_at`, `reviewed_at`, `reviewed_by_user_id`, `rejection_reason`; status on row. No generic audit table |
| Resource status | `updated_at` + optional note in `resources` later; no history table |
| Enterprise `audits` package | **No** |

`reservation_events`: `from_status`, `to_status`, `actor_user_id` NULL for jobs, `source` (`api_customer` \| `api_owner` \| `system_job` \| `admin`), `reason`, `created_at`. Insert in the same transaction as the status update.

---

## 24. Full Entity Relationship Model

### users

- **Purpose:** Account; customer capability; platform admin flag  
- **MVP:** Yes  
- **PK:** `id` uuid  
- **Columns:** name, phone, email, password, avatar_url, locale, platform_role, status, phone_verified_at, email_verified_at, last_login_at, deleted_at, timestamps  
- **FKs:** none  
- **Constraints:** unique phone; unique email; CHECKs on locale, platform_role, status  

### business_categories

- **Purpose:** Platform taxonomy  
- **MVP:** Yes  
- **PK:** `id` uuid  
- **Columns:** slug, name jsonb, icon, sort_order, is_active, timestamps  
- **FKs:** none  
- **Constraints:** unique slug; name is object  

### businesses

- **Purpose:** Public venue  
- **MVP:** Yes  
- **PK:** `id` uuid  
- **Columns:** category_id, created_by_user_id, status, name, description, translations, phone, email, country_code, region, city, district, address_line, latitude, longitude, timezone, cover_image_url, rejection_reason, submitted_at, reviewed_at, reviewed_by_user_id, deleted_at, timestamps  
- **FKs:** category RESTRICT; created_by RESTRICT; reviewed_by SET NULL  
- **Constraints:** status CHECK; geo CHECK; public index on status+city  

### business_members

- **Purpose:** Per-venue owner/manager/staff  
- **MVP:** Yes  
- **PK:** `id` uuid  
- **Columns:** business_id, user_id, member_role, status, timestamps  
- **FKs:** business CASCADE; user RESTRICT  
- **Constraints:** unique (business_id, user_id); role/status CHECK  

### booking_policies

- **Purpose:** Booking rules 1:1  
- **MVP:** Yes  
- **PK:** `id` uuid  
- **Columns:** business_id, confirmation_mode, duration/advance/cancel/pending/check-in/no-show/buffer integers, timestamps  
- **FKs:** business CASCADE unique  
- **Constraints:** min_duration ≤ max_duration; positive integers  

### business_hours

- **Purpose:** Weekly local schedule  
- **MVP:** Yes  
- **PK:** `id` uuid  
- **Columns:** business_id, weekday, is_closed, is_open_24h, opens_at, closes_at  
- **FKs:** business CASCADE  
- **Constraints:** unique (business_id, weekday); weekday 1–7; closure CHECKs  

### resource_groups

- **Purpose:** Zone/hall  
- **MVP:** Yes  
- **PK:** `id` uuid  
- **Columns:** business_id, name, sort_order, deleted_at, timestamps  
- **FKs:** business CASCADE  
- **Constraints:** unique (business_id, name) WHERE deleted_at IS NULL  

### resources

- **Purpose:** Bookable unit  
- **MVP:** Yes  
- **PK:** `id` uuid  
- **Columns:** business_id, resource_group_id, name, code, resource_type, status, capacity, hourly_rate_amount, currency, metadata, sort_order, deleted_at, timestamps  
- **FKs:** business RESTRICT; group SET NULL; **UNIQUE (id, business_id)** for composite FK from reservations  
- **Constraints:** unique (business_id, code) WHERE deleted_at IS NULL; type/status CHECK; active ⇒ rate NOT NULL  

### reservations

- **Purpose:** Booking + occupancy  
- **MVP:** Yes  
- **PK:** `id` uuid  
- **Columns:** see §26  
- **FKs:** customer RESTRICT; **(resource_id, business_id)** → resources (id, business_id) RESTRICT  
- **Constraints:** EXCLUDE occupancy; unique idempotency_key; status CHECK; end > start  

### reservation_events

- **Purpose:** Status audit  
- **MVP:** Yes  
- **PK:** `id` uuid  
- **Columns:** reservation_id, from_status, to_status, actor_user_id, source, reason, created_at  
- **FKs:** reservation CASCADE; actor SET NULL  
- **Constraints:** to_status CHECK  

**Not MVP entities:** Payment, Notification, Review, Favorite, Branch, Brand, PriceRule, ResourceUnavailability, Role, Permission.

---

## 25. ERD IN TEXT FORMAT

```
USERS
  ├── (platform_role, locale, phone unique)
  ├── BUSINESS_MEMBERS (member_role: owner|manager|staff)
  │     └── BUSINESSES
  │           ├── BUSINESS_CATEGORIES (name jsonb uz/kaa/ru)
  │           ├── BOOKING_POLICIES (1:1)
  │           ├── BUSINESS_HOURS (7 × weekday, overnight via opens>closes)
  │           ├── RESOURCE_GROUPS (zone)
  │           │     └── RESOURCES (metadata jsonb, hourly_rate_amount)
  │           │           └── RESERVATIONS
  │           └── RESERVATIONS (denormalized business_id)
  │                 └── RESERVATION_EVENTS
  └── RESERVATIONS (as customer_id)

CONSTRAINT (PostgreSQL):
  RESERVATIONS EXCLUDE gist (resource_id =, occupancy_range &&)
    WHERE status IN (pending, confirmed, checked_in)
```

```
USERS 1 ──< BUSINESS_MEMBERS >── 1 BUSINESSES
USERS 1 ──< RESERVATIONS (customer)
BUSINESSES 1 ──< RESERVATIONS
RESOURCES 1 ──< RESERVATIONS
BUSINESSES 1 ── 1 BOOKING_POLICIES
BUSINESSES 1 ──< BUSINESS_HOURS
BUSINESSES 1 ──< RESOURCE_GROUPS 1 ──< RESOURCES
BUSINESSES 1 ──< RESOURCES
BUSINESS_CATEGORIES 1 ──< BUSINESSES
RESERVATIONS 1 ──< RESERVATION_EVENTS
```

---

## 26. Table-by-Table Schema Proposal

Conventions: `created_at`/`updated_at` `timestamptz NOT NULL`. PK `id uuid NOT NULL`. No migration PHP.

### 26.1 `users`

| Column | Type | Null | Default | Description | Constraint / index |
|---|---|---|---|---|---|
| id | uuid | NO | — | PK | PK |
| name | varchar(120) | NO | — | Display name | |
| phone | varchar(16) | NO | — | E.164 | UNIQUE |
| email | varchar(255) | YES | NULL | Optional | UNIQUE |
| password | varchar(255) | NO | — | Hash | |
| avatar_url | text | YES | NULL | | |
| locale | varchar(8) | NO | `'ru'` | uz/kaa/ru | CHECK |
| platform_role | varchar(32) | NO | `'user'` | user/platform_admin | CHECK |
| status | varchar(32) | NO | `'active'` | active/blocked | CHECK |
| phone_verified_at | timestamptz | YES | NULL | | |
| email_verified_at | timestamptz | YES | NULL | | |
| last_login_at | timestamptz | YES | NULL | | |
| deleted_at | timestamptz | YES | NULL | Soft delete | INDEX |
| created_at, updated_at | timestamptz | NO | now() | | |

Index: `(status)` optional; `(locale)` no.

### 26.2 `business_categories`

| Column | Type | Null | Default | Description | Constraint / index |
|---|---|---|---|---|---|
| id | uuid | NO | | PK | PK |
| slug | varchar(64) | NO | | gaming_club | UNIQUE |
| name | jsonb | NO | | {uz,kaa,ru} | CHECK object |
| icon | varchar(64) | YES | NULL | Icon key | |
| sort_order | integer | NO | 0 | | INDEX |
| is_active | boolean | NO | true | | INDEX (is_active) |
| created_at, updated_at | timestamptz | NO | | | |

### 26.3 `businesses`

| Column | Type | Null | Default | Description | Constraint / index |
|---|---|---|---|---|---|
| id | uuid | NO | | PK | PK |
| category_id | uuid | NO | | | FK RESTRICT, INDEX |
| created_by_user_id | uuid | NO | | Submitter | FK RESTRICT |
| status | varchar(32) | NO | `'draft'` | draft/pending_review/approved/rejected/suspended/archived | CHECK; **partial INDEX (status) WHERE deleted_at IS NULL** |
| name | varchar(180) | NO | | Listing name | btree for `ILIKE` later: `pg_trgm` **not** MVP; simple `lower(name)` optional |
| description | text | YES | NULL | | |
| translations | jsonb | YES | NULL | Optional i18n | |
| phone | varchar(16) | YES | NULL | Venue phone | |
| email | varchar(255) | YES | NULL | | |
| country_code | char(2) | NO | `'UZ'` | | |
| region | varchar(120) | YES | NULL | | |
| city | varchar(120) | YES | NULL | | INDEX (city) |
| district | varchar(120) | YES | NULL | | |
| address_line | varchar(255) | YES | NULL | | |
| latitude | numeric(9,6) | YES | NULL | | CHECK with lng |
| longitude | numeric(9,6) | YES | NULL | | |
| timezone | varchar(64) | NO | `'Asia/Tashkent'` | IANA | |
| cover_image_url | text | YES | NULL | | |
| rejection_reason | text | YES | NULL | Admin | |
| submitted_at | timestamptz | YES | NULL | | |
| reviewed_at | timestamptz | YES | NULL | | |
| reviewed_by_user_id | uuid | YES | NULL | | FK SET NULL |
| deleted_at | timestamptz | YES | NULL | | INDEX |
| created_at, updated_at | timestamptz | NO | | | |

Composite: `(status, city)` WHERE `status = 'approved' AND deleted_at IS NULL`.

### 26.4 `business_members`

| Column | Type | Null | Default | Description | Constraint / index |
|---|---|---|---|---|---|
| id | uuid | NO | | PK | PK |
| business_id | uuid | NO | | | FK CASCADE, INDEX |
| user_id | uuid | NO | | | FK RESTRICT, INDEX |
| member_role | varchar(32) | NO | `'owner'` | owner/manager/staff | CHECK |
| status | varchar(32) | NO | `'active'` | active/revoked | CHECK |
| created_at, updated_at | timestamptz | NO | | | |

UNIQUE (business_id, user_id). INDEX (user_id) WHERE status = 'active' (mode switcher).

### 26.5 `booking_policies`

| Column | Type | Null | Default | Description | Constraint / index |
|---|---|---|---|---|---|
| id | uuid | NO | | PK | PK |
| business_id | uuid | NO | | | UNIQUE, FK CASCADE |
| confirmation_mode | varchar(16) | NO | `'instant'` | instant/manual | CHECK |
| min_duration_minutes | integer | NO | 60 | | CHECK > 0 |
| max_duration_minutes | integer | NO | 480 | | CHECK >= min |
| duration_step_minutes | integer | NO | 60 | | CHECK > 0 |
| cancellation_deadline_minutes | integer | NO | 60 | Before start | CHECK >= 0 |
| pending_expiry_minutes | integer | NO | 30 | | CHECK > 0 |
| check_in_early_minutes | integer | NO | 15 | | CHECK >= 0 |
| no_show_grace_minutes | integer | NO | 20 | | CHECK >= 0 |
| buffer_minutes | integer | NO | 0 | Occupancy padding | CHECK >= 0 |
| min_advance_minutes | integer | NO | 0 | | CHECK >= 0 |
| max_advance_days | integer | NO | 14 | | CHECK > 0 |
| created_at, updated_at | timestamptz | NO | | | |

### 26.6 `business_hours`

| Column | Type | Null | Default | Description | Constraint / index |
|---|---|---|---|---|---|
| id | uuid | NO | | PK | PK |
| business_id | uuid | NO | | | FK CASCADE |
| weekday | smallint | NO | | 1–7 | CHECK; UNIQUE with business_id |
| is_closed | boolean | NO | false | | |
| is_open_24h | boolean | NO | false | | |
| opens_at | time | YES | NULL | Local | |
| closes_at | time | YES | NULL | Local; may be < opens_at | |

CHECK closed/24h/times consistency. INDEX (business_id).

### 26.7 `resource_groups`

| Column | Type | Null | Default | Description | Constraint / index |
|---|---|---|---|---|---|
| id | uuid | NO | | PK | PK |
| business_id | uuid | NO | | | FK CASCADE, INDEX |
| name | varchar(120) | NO | | VIP | |
| sort_order | integer | NO | 0 | | |
| deleted_at | timestamptz | YES | NULL | | |
| created_at, updated_at | timestamptz | NO | | | |

UNIQUE (business_id, name) WHERE deleted_at IS NULL.

### 26.8 `resources`

| Column | Type | Null | Default | Description | Constraint / index |
|---|---|---|---|---|---|
| id | uuid | NO | | PK | PK |
| business_id | uuid | NO | | | FK RESTRICT, INDEX |
| resource_group_id | uuid | YES | NULL | | FK SET NULL |
| name | varchar(120) | NO | | PC #01 | |
| code | varchar(64) | NO | | PC-01 | unique with business |
| resource_type | varchar(32) | NO | `'other'` | pc/console/… | CHECK |
| status | varchar(32) | NO | `'active'` | active/inactive/maintenance | CHECK |
| capacity | integer | NO | 1 | | CHECK > 0 |
| hourly_rate_amount | bigint | YES | NULL | UZS / hour | CHECK >= 0; NOT NULL if active |
| currency | char(3) | NO | `'UZS'` | | |
| metadata | jsonb | NO | `'{}'` | Specs | CHECK object |
| sort_order | integer | NO | 0 | | |
| deleted_at | timestamptz | YES | NULL | | INDEX |
| created_at, updated_at | timestamptz | NO | | | |

UNIQUE (id, business_id). UNIQUE (business_id, code) WHERE deleted_at IS NULL. INDEX (business_id, status) WHERE deleted_at IS NULL.

### 26.9 `reservations`

| Column | Type | Null | Default | Description | Constraint / index |
|---|---|---|---|---|---|
| id | uuid | NO | | PK | PK |
| customer_id | uuid | NO | | | FK RESTRICT, INDEX |
| business_id | uuid | NO | | Denorm | composite FK |
| resource_id | uuid | NO | | | composite FK |
| start_at | timestamptz | NO | | UTC | |
| end_at | timestamptz | NO | | UTC | CHECK > start_at |
| duration_minutes | integer | NO | | Must match end-start | CHECK > 0 |
| occupancy_range | tstzrange | NO | | [start, end+buffer) | GiST EXCLUDE |
| buffer_minutes_applied | integer | NO | 0 | Snapshot | CHECK >= 0 |
| status | varchar(32) | NO | | see §16 | CHECK; part of EXCLUDE |
| hourly_rate_amount | bigint | NO | | Snapshot | CHECK >= 0 |
| total_amount | bigint | NO | | Snapshot | CHECK >= 0 |
| currency | char(3) | NO | `'UZS'` | Snapshot | |
| confirmation_mode | varchar(16) | NO | | Snapshot | CHECK |
| payment_status | varchar(32) | NO | `'pay_at_venue'` | | CHECK |
| payment_method | varchar(32) | NO | `'venue'` | | |
| notes | varchar(500) | YES | NULL | Customer | |
| cancellation_reason | varchar(500) | YES | NULL | | |
| rejection_reason | varchar(500) | YES | NULL | | |
| cancelled_by_user_id | uuid | YES | NULL | | FK SET NULL |
| cancelled_by_actor_type | varchar(32) | YES | NULL | customer/owner/staff/admin/system | |
| checked_in_at | timestamptz | YES | NULL | | |
| completed_at | timestamptz | YES | NULL | | |
| expires_at | timestamptz | YES | NULL | Pending TTL | INDEX partial pending |
| idempotency_key | uuid | NO | | Client key | UNIQUE |
| created_at, updated_at | timestamptz | NO | | | |

Indexes: see §27. FK `(resource_id, business_id)` → `resources (id, business_id)` ON DELETE RESTRICT.

CHECK: `duration_minutes = (EXTRACT(EPOCH FROM (end_at - start_at)) / 60)::int` (require whole minutes).

### 26.10 `reservation_events`

| Column | Type | Null | Default | Description | Constraint / index |
|---|---|---|---|---|---|
| id | uuid | NO | | PK | PK |
| reservation_id | uuid | NO | | | FK CASCADE, INDEX |
| from_status | varchar(32) | YES | NULL | Null on create | |
| to_status | varchar(32) | NO | | | CHECK |
| actor_user_id | uuid | YES | NULL | | FK SET NULL |
| source | varchar(32) | NO | | api_customer/api_owner/system_job/admin | CHECK |
| reason | text | YES | NULL | | |
| created_at | timestamptz | NO | now() | No updated_at | |

### 26.11 Laravel framework tables (not redesigned)

`password_reset_tokens`, `sessions` (if used), `personal_access_tokens` (Sanctum), `jobs`/`failed_jobs`/`job_batches` as Laravel defaults. Use **bigint** PKs there if Laravel default; do not UUID-ify Sanctum unless needed.

---

## 27. Complete Indexing Strategy

| Table | Index | Why |
|---|---|---|
| users | UNIQUE phone | Login |
| users | UNIQUE email | Optional login |
| users | deleted_at | Soft-delete scopes |
| business_categories | UNIQUE slug | Lookups |
| business_categories | (is_active, sort_order) | Home chips |
| businesses | category_id | Filter |
| businesses | **partial (id) WHERE status='approved' AND deleted_at IS NULL** | Optional; better **(city, status)** partial approved |
| businesses | city | City filter |
| businesses | (latitude, longitude) | Later bbox; optional MVP skip |
| business_members | UNIQUE (business_id, user_id) | Integrity |
| business_members | (user_id, status) | “Does user have owner mode?” |
| booking_policies | UNIQUE business_id | 1:1 |
| business_hours | UNIQUE (business_id, weekday) | Load week |
| resource_groups | (business_id) | List zones |
| resources | (business_id, status) WHERE deleted_at IS NULL | Public list |
| resources | UNIQUE (business_id, code) WHERE deleted_at IS NULL | Codes |
| resources | UNIQUE (id, business_id) | Composite FK |
| reservations | EXCLUDE gist | **Occupancy** |
| reservations | (customer_id, start_at DESC) | My bookings |
| reservations | (business_id, start_at) | Owner dashboard |
| reservations | (resource_id, start_at) | Availability read |
| reservations | **partial (status, expires_at) WHERE status='pending'** | Expiry job |
| reservations | **partial (status, start_at) WHERE status IN ('confirmed','checked_in')** | Now/upcoming |
| reservations | UNIQUE idempotency_key | Retries |
| reservation_events | (reservation_id, created_at) | Timeline |

**No** GiST on businesses. **No** full-text until search quality demands `pg_trgm`.

Availability read: `WHERE resource_id = $1 AND occupancy_range && $day_range::tstzrange AND status IN (…)`. EXCLUDE already creates a GiST index usable for `&&`.

---

## 28. Soft Delete and Retention Strategy

| Entity | Strategy | Why |
|---|---|---|
| User | **Soft delete** (`deleted_at`) + `status=blocked` for bans | Reservations keep `customer_id`; GDPR later = anonymize name/phone |
| Business | **Soft delete** + `archived`/`suspended` | Listing hidden; reservations remain |
| Business member | **Hard delete or status=revoked** (prefer **revoked**, no deleted_at) | Audit who had access |
| Resource group | Soft delete | Resources SET NULL group |
| Resource | **Soft delete** + status | Cannot hard-delete if reservations **RESTRICT**; hide from catalog |
| Reservation | **Never soft-delete.** Status only | Occupancy predicate stays simple; history intact |
| Reservation event | **Never delete** (except CASCADE with reservation — and reservations are not deleted) | |
| Category | Deactivate `is_active`; **RESTRICT** delete if businesses exist | |
| Policy / hours | Replaced in place; CASCADE if business hard-deleted (won’t happen in MVP) | |

**Phone uniqueness after user soft-delete:** keep **global UNIQUE(phone)** so a deleted number cannot be hijacked. Restoration = clear `deleted_at`.

**Hard-delete reservations:** not allowed in product. Operational purge only with legal/ops policy years later.

---

## 29. Laravel Migration Plan

**Do not generate PHP in this prompt.** Order:

1. **Enable `btree_gist`** (`CREATE EXTENSION IF NOT EXISTS btree_gist`). Reversible: `DROP EXTENSION` only if unused.  
2. Laravel default `users` **replaced/altered** to this schema (or first migration creates `users` as specified — avoid double users table).  
3. `password_reset_tokens`, Sanctum `personal_access_tokens`.  
4. `business_categories` + seed uz/kaa/ru.  
5. `businesses`.  
6. `business_members`.  
7. `booking_policies`.  
8. `business_hours`.  
9. `resource_groups`.  
10. `resources` including `UNIQUE (id, business_id)`.  
11. `reservations` columns + CHECKs + FKs + btree indexes + **raw SQL EXCLUDE**.  
12. `reservation_events`.  
13. Queue tables if using database driver.

**Reversibility:** drop EXCLUDE then tables in reverse order. Extension drop last.

**Laravel limitations:** use `DB::statement` for EXCLUDE, partial unique indexes (`CREATE UNIQUE INDEX … WHERE deleted_at IS NULL`), composite FK `(resource_id, business_id)`, `tstzrange`. Schema builder can create `uuid`, `jsonb`, `timestamptz`, `time`, `geography` **not used**.

**UUID generation:** application (Laravel v7); columns `uuid` without `gen_random_uuid()` default required (optional default `uuidv7()` if PG 18+ / extension — **do not depend on it**; Laravel fills ids).

---

## 30. Database Edge Cases

| Case | Handling |
|---|---|
| Two overlapping inserts | Lock + EXCLUDE; 409 |
| Adjacent 18–21 and 21–23 | Half-open range; allowed |
| Buffer 15 min | occupancy end extended; may block adjacent |
| Pending expires | Job sets `expired`; EXCLUDE drops row from predicate |
| Confirm pending | Same occupying set; self-update OK |
| Resource maintenance with future occupying bookings | App **blocks** status change (PRD); DB does not auto-cancel |
| Change resource `business_id` | **Forbidden** (breaks denorm); no UPDATE |
| Overnight booking | One row, UTC instants |
| `duration_minutes` mismatch | CHECK fails insert |
| Duplicate tap | Same `idempotency_key` unique violation → fetch existing |
| Filament raw SQL insert overlap | EXCLUDE error |
| Delete user with bookings | RESTRICT; soft-delete instead |
| Delete resource with history | RESTRICT; soft-delete |
| Price change | Snapshot unchanged |
| Clock skew | Server UTC only |
| `completed` overlapping new booking in the past | completed not occupying; new booking must still be in the future |

---

## 31. Future Expansion Strategy

| Feature | Additive schema |
|---|---|
| Branches/chains | `brands` + `businesses.brand_id` |
| Timed blackouts | `resource_unavailability` + range EXCLUDE or merge into availability SQL |
| Peak pricing | `price_rules` |
| Multi-resource checkout | `booking_group_id` on reservations |
| Payments | `payments` table; reservation payment_status |
| FCM | `notifications` (Laravel) + `device_tokens` |
| Reviews / favorites | new tables |
| PostGIS | `geography(Point,4326)` generated from lat/lng |
| Extra language | new key inside JSONB |
| Per-resource hours | `resource_hours` later |

---

## 32. Explicit Assumptions

1. PostgreSQL 16+ in all environments.  
2. UUID v7 generated by Laravel, type `uuid`.  
3. UZS integer amounts; no tiyin.  
4. UI languages uz/kaa/ru (this prompt **supersedes** PRD “English keys first” for product languages; English UI may still exist later in Flutter only).  
5. Category JSONB always has uz, kaa, ru.  
6. Business listing name is a single string unless owner fills `translations`.  
7. No PostGIS, no payments/reviews/favorites/notifications tables.  
8. `buffer_minutes` column exists default 0 (policy + snapshot on reservation).  
9. ISO weekdays 1–7.  
10. Half-open occupancy `[start, end)`.  
11. Sanctum tables use Laravel defaults.  
12. One reservation = one resource.  
13. `APP_TIMEZONE=UTC`.  
14. Default user locale `ru`.  
15. Composite FK enforces reservation.business_id = resource.business_id.

---

## 33. Final Recommended Database Architecture

A **normalized PostgreSQL schema** with ten domain tables, UUID PKs, UTC instants, integer money, JSONB only for category names and resource specs, membership-based authorization, weekly hours with overnight, and **GiST exclusion on occupying reservations** as the occupancy source of truth. Laravel owns transactions, locks, validation, and state machine; the database **refuses** conflicting occupying rows even if the app is wrong.

---

## 34. DATABASE IMPLEMENTATION CONTEXT FOR NEXT PROMPT

**Next prompt should generate Laravel migrations (and only then models), inheriting:**

**Tables (in order):** extension `btree_gist` → `users` (custom) → Laravel auth/Sanctum → `business_categories` → `businesses` → `business_members` → `booking_policies` → `business_hours` → `resource_groups` → `resources` → `reservations` + **raw EXCLUDE** → `reservation_events`.

**IDs:** `uuid` PK, ordered UUID from app.

**Statuses occupying:** `pending`, `confirmed`, `checked_in`.  
**Not occupying:** `cancelled`, `rejected`, `expired`, `completed`, `no_show`.

**EXCLUDE:** `gist (resource_id WITH =, occupancy_range WITH &&) WHERE status IN (pending, confirmed, checked_in)`.

**occupancy_range:** stored `tstzrange` `[start_at, end_at + buffer_minutes_applied)`.

**Money:** `bigint` + `currency`.

**Roles:** `users.platform_role`; `business_members`; never a single XOR role.

**FKs:** reservations RESTRICT on user/business/resource; composite `(resource_id, business_id)`; hours/policy/members CASCADE from business; group SET NULL on resources.

**i18n:** category `name` JSONB uz/kaa/ru; Flutter for UI strings.

**Do not add:** payments, reviews, favorites, notifications, PostGIS, computers table, branches.

**MUST NOT change without explicit justification:** exclusion occupying set; integer UZS; timestamptz UTC; generic resources; UUID PKs; pending occupies; half-open ranges; no soft-delete on reservations.

**Read:** `docs/PRD-MVP-BLUEPRINT.md` and this file `docs/DATABASE-ARCHITECTURE.md`.

---

*End of database architecture document.*
