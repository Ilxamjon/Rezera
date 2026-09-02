# Rezera — Backend Architecture

**Status:** Source of truth for Laravel backend implementation  
**Depends on:** `docs/PRD.md`, `docs/DATABASE-ARCHITECTURE.md`  
**Laravel version:** 11.x  
**Last updated:** 2026-09-01

---

## 1. Overview

Rezera uses a **modular monolith** Laravel API. One deployable application serves the Flutter mobile client via versioned REST endpoints (`/api/v1`). Business logic for bookings, businesses, and resources will be added incrementally in future prompts.

This document describes the **foundation** established in Prompt #3. Do not redesign product or database decisions documented elsewhere.

---

## 2. Project structure

```
app/
├── Domain/                    # Enums and future domain value objects
│   ├── Identity/
│   └── Businesses/
├── Exceptions/
│   └── ApiExceptionRenderer.php
├── Http/
│   ├── Controllers/Api/V1/    # Versioned API controllers
│   └── Middleware/
├── Models/                    # Eloquent models
├── Policies/                  # Authorization policies (expand per entity)
├── Providers/
├── Services/
│   └── Authorization/         # Business-scoped auth helpers
└── Support/                   # Cross-cutting utilities
    ├── Api/                   # ApiResponse helper
    ├── Database/              # PostgreSQL migration helpers
    ├── Localization/
    ├── Money/
    ├── Phone/
    └── Time/

routes/
├── api.php                    # Loads /api/v1/*
└── api/v1/
    ├── public.php             # Unauthenticated routes
    ├── auth.php               # Authentication (future)
    ├── customer.php           # Customer routes (future)
    ├── owner.php              # Business management (future)
    └── admin.php              # Platform admin API (future)
```

**Intentionally not created yet:** empty `Actions/`, `Http/Requests/`, `Http/Resources/` trees. Add per feature when implementing endpoints.

---

## 3. Modular domains (logical)

| Module | Responsibility | Status |
|---|---|---|
| Identity | Users, auth, platform roles | Foundation only |
| Businesses | Venues, members, hours, policies | Not implemented |
| Resources | Groups, inventory | Not implemented |
| Reservations | Booking engine, state machine | Not implemented |
| Availability | Slot generation | Not implemented |
| Pricing | Hourly quotes | Utility only (`Money`) |
| Notifications | Push/email | Not implemented |
| Administration | Filament / admin API | Middleware only |

---

## 4. API versioning

- Prefix: **`/api/v1/`**
- Route names: `api.v1.*`
- Breaking changes require `/api/v2/`
- Additive JSON fields are non-breaking

Route files are split by **access level** to prevent accidental exposure of admin routes.

---

## 5. API response format

### Success

```json
{
  "success": true,
  "message": null,
  "data": {}
}
```

### Paginated success

`data` contains:

```json
{
  "items": [],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 0
  }
}
```

### Error

```json
{
  "success": false,
  "message": "Human-readable message",
  "errors": {
    "field": ["Validation message"]
  },
  "code": "machine_readable_code"
}
```

Use `App\Support\Api\ApiResponse` or extend `BaseApiController`.

---

## 6. Exception handling

`App\Exceptions\ApiExceptionRenderer` handles API requests (`api/*` or `Accept: application/json`):

| Exception | HTTP | Code |
|---|---|---|
| ValidationException | 422 | `validation_failed` |
| AuthenticationException | 401 | `unauthenticated` |
| AuthorizationException | 403 | `forbidden` |
| ModelNotFoundException | 404 | `not_found` |
| NotFoundHttpException | 404 | `route_not_found` |
| TooManyRequestsHttpException | 429 | `too_many_requests` |
| QueryException (23P01 exclusion) | 409 | `resource_not_available` |
| Other QueryException | 409 | `database_error` |
| Unhandled | 500 | `server_error` |

Production responses never include stack traces. Unexpected errors are logged.

---

## 7. Authentication

- **Guard:** `auth:sanctum` for mobile API
- **Package:** Laravel Sanctum (personal access tokens)
- **User model:** `App\Models\User` with `HasApiTokens`, UUID PK (`HasUuids`)
- **Token lifecycle:** Sanctum default (no expiration in MVP config; tokens revoked on logout)
- **Login/register:** Not implemented in foundation prompt — routes reserved in `routes/api/v1/auth.php`

### User identity fields (aligned with database doc)

- Primary identifier: **phone** (E.164)
- `platform_role`: `user` | `platform_admin`
- `locale`: `uz` | `kaa` | `ru`
- `status`: `active` | `blocked`
- Soft deletes via `deleted_at`

Phone normalization: `App\Support\Phone\PhoneNormalizer`

---

## 8. Authorization

### Platform level

- `users.platform_role = platform_admin` → Gate `platform-admin`
- Middleware `platform.admin` for `/api/v1/admin/*`

### Business level

- **`business_members`** table (not migrated yet) will hold `owner` | `manager` | `staff`
- `App\Services\Authorization\BusinessAuthorizationService` — stub until model exists
- Future policies: `BusinessPolicy`, `ResourcePolicy`, `ReservationPolicy` extending `BasePolicy`

**Rule:** Never conflate platform admin with business owner. A user can be both customer and owner.

---

## 9. PostgreSQL compatibility

Production and tests target **PostgreSQL only** for domain logic.

Laravel Schema Builder is used for standard tables. PostgreSQL-specific features use **`DB::statement`** via `App\Support\Database\PostgresMigration`:

- `CREATE EXTENSION btree_gist`
- `tstzrange` columns
- `EXCLUDE USING gist` for reservation occupancy
- Partial unique indexes

**Never** replace exclusion constraints with application-only checks.

SQLite is **not** suitable for booking integration tests.

---

## 10. Timezone convention

| Layer | Convention |
|---|---|
| `config/app.php` | `APP_TIMEZONE=UTC` |
| Database instants | `timestamptz` stored as UTC |
| Business hours | Local `time` + weekday in `businesses.timezone` |
| API output | ISO 8601 UTC; clients convert to venue timezone |
| Helpers | `App\Support\Time\Timezone` |

---

## 11. Money convention

- Integer **UZS** amounts (`bigint`)
- No floats in PHP business logic
- Helper: `App\Support\Money\Money::hourlyTotal()`
- Reservations must snapshot price at creation (future implementation)

---

## 12. Multilingual support

| Layer | Approach |
|---|---|
| Flutter UI | ARB localization (uz, kaa, ru) |
| User preference | `users.locale` |
| Category names | JSONB `{uz,kaa,ru}` in database (future migration) |
| Business content | Owner `name`/`description`; optional `translations` JSONB later |
| API errors | Laravel `lang/` files (`en`, `ru`; extend uz/kaa as needed) |
| Resolver | `App\Support\Localization\LocaleResolver::pick()` for JSONB content |

Do not hardcode business-facing content in PHP.

---

## 13. CORS

Configured in `config/cors.php` via `CORS_ALLOWED_ORIGINS` (comma-separated).

| Environment | Example |
|---|---|
| Local | `http://localhost:3000` |
| Staging | `https://staging.rezera.uz` |
| Production | Explicit Flutter/web origins only — never `*` with credentials |

---

## 14. Rate limiting

Configured in `AppServiceProvider`:

| Limiter | Default | Use |
|---|---|---|
| `api` | 60/min | General authenticated API |
| `auth` | 10/min | Login/register (future) |
| `booking` | 20/min | Reservation create (future) |

Apply with `throttle:auth` etc. on route groups.

---

## 15. Queue and scheduling

- **Default queue driver:** `database` (no Redis required for MVP local dev)
- **Production recommendation:** Redis or managed queue + `php artisan queue:work`
- **Future jobs:** expire pending reservations, reminders, FCM
- **Scheduling:** add commands in `routes/console.php` or `app/Console/Commands/` and register in `bootstrap/app.php` when needed

Not implemented in foundation prompt.

---

## 16. Logging and observability

- Channel: `stack` → `single` file (`storage/logs/laravel.log`)
- Log database errors without exposing SQL to clients
- Never log passwords or bearer tokens
- **Future:** Sentry/Bugsnag integration point in exception renderer

---

## 17. Testing strategy

| Type | Location | Notes |
|---|---|---|
| Feature/API | `tests/Feature/Api/V1/` | HTTP tests |
| Unit | `tests/Unit/` | Pure helpers |
| Domain | Future `tests/Unit/Domain/` | Booking rules |

**Database:** `phpunit.xml` uses `pgsql` + `rezera_test`. Create DB before running full suite.

**Critical:** Overlap/exclusion tests must run on PostgreSQL after migrations land.

Smoke test: `HealthEndpointTest` (no DB required).

---

## 18. Code quality

- **Laravel Pint** — `composer format`
- **PHPUnit 11** — `composer test`
- No PHPStan in foundation (add later if needed)

---

## 19. Future module development rules

1. Read PRD + DATABASE-ARCHITECTURE before coding.
2. Thin controllers; Form Requests for validation; API Resources for responses.
3. Booking invariants in dedicated services, not controllers.
4. Use transactions + `lockForUpdate` + idempotency for reservation create.
5. Map PostgreSQL `23P01` to 409 `resource_not_available`.
6. Do not add packages without justification.
7. Do not change occupying statuses or exclusion rules without explicit review.
8. Preserve `/api/v1` response envelope.
9. Owner routes must scope by `business_id` membership.
10. Money stays integer; time stays UTC in storage.

---

## 20. Health endpoint

`GET /api/v1/health` — public, no sensitive infrastructure details.

```json
{
  "success": true,
  "message": null,
  "data": {
    "status": "ok",
    "service": "Rezera",
    "version": "v1"
  }
}
```

Laravel built-in `GET /up` also exists for infrastructure probes.

---

*End of backend architecture document.*
