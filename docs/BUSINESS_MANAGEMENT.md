# Rezera — Business & Venue Management API

**Status:** Implemented (Prompt #6)  
**Depends on:** `docs/BACKEND_ARCHITECTURE.md`, `docs/DATABASE_IMPLEMENTATION.md`, `docs/AUTHENTICATION.md`

---

## 1. Module overview

The Business module provides a **generic venue management API** for Rezera. One `Business` model serves gaming clubs, restaurants, cafes, coworking spaces, and future venue types. Differentiation happens through `business_categories`, not separate APIs.

The module covers:

- Business categories (public discovery)
- Business creation (authenticated)
- Public listing and details (customer discovery)
- Management endpoints (owners, managers, staff)
- Membership management
- Working hours management

**Not implemented in this prompt:** resources, reservations, availability, payments, reviews, favorites, admin moderation UI.

---

## 2. Public vs management API

| Audience | Prefix | Authentication | Visibility |
|---|---|---|---|
| Customers / guests | `/api/v1/businesses`, `/api/v1/business-categories` | None | Only **approved** businesses |
| Authenticated users | `POST /api/v1/businesses` | `auth:sanctum` | Creates draft business |
| Business members | `/api/v1/manage/businesses/*` | `auth:sanctum` | Businesses where user has active membership |

Management endpoints never rely on route naming alone — every action is authorized through `BusinessPolicy` and `BusinessAuthorizationService`.

---

## 3. Business category endpoint

**`GET /api/v1/business-categories`**

Returns active categories from the database (`is_active = true`), ordered by `sort_order`, then `slug`.

**Response fields:**

| Field | Description |
|---|---|
| `id` | UUID |
| `slug` | Stable identifier |
| `name` | Multilingual object `{ uz, kaa, ru }` |
| `localized_name` | Resolved string using `Accept-Language` header |
| `icon` | Icon key |
| `sort_order` | Display order |

Categories are seeded via `BusinessCategorySeeder` — not hardcoded in controllers.

---

## 4. Business creation

**`POST /api/v1/businesses`** (authenticated)

### Flow (`CreateBusinessAction`)

1. Start database transaction
2. Create `businesses` row with `status = draft`
3. Create `business_members` row for creator with `member_role = owner`, `status = active`
4. Create default `booking_policies` row (`confirmation_mode = instant`)
5. Commit and return management resource

### Rules

- Creator becomes **business-scoped owner** via `business_members` — no global `business_owner` platform role is assigned
- Initial status is always `draft`
- All writes are atomic (transaction)

### Request fields

| Field | Required | Notes |
|---|---|---|
| `category_id` | Yes | UUID, must exist |
| `name` | Yes | max 180 |
| `description` | No | |
| `translations` | No | `{ uz?, kaa?, ru? }` optional multilingual description |
| `phone`, `email` | No | |
| `country_code` | No | default `UZ` |
| `region`, `city`, `district`, `address_line` | No | |
| `latitude`, `longitude` | No | Must be provided together |
| `timezone` | No | default `Asia/Tashkent`, validated IANA |
| `cover_image_url` | No | URL |

---

## 5. Business listing (public)

**`GET /api/v1/businesses`**

### Visibility

Only businesses where:

- `status = approved`
- `deleted_at IS NULL`

### Filters

| Parameter | Description |
|---|---|
| `category_id` | Filter by category UUID |
| `city` | Case-insensitive partial match (`ilike`) |
| `search` | Partial match on `name` or `description` |
| `per_page` | 1–50, default 15 |

### Pagination format

```json
{
  "success": true,
  "message": null,
  "data": {
    "items": [ ... ],
    "meta": {
      "current_page": 1,
      "last_page": 3,
      "per_page": 15,
      "total": 42
    }
  }
}
```

Internal fields (`status`, `rejection_reason`, moderation metadata) are **not** exposed in public resources.

---

## 6. Business details (public)

**`GET /api/v1/businesses/{business}`**

Returns `PublicBusinessResource` when:

- Business is publicly visible (`approved` + not soft-deleted), **or**
- Authenticated user is an active member (can preview draft/pending businesses they manage)

Includes category and working hours when loaded. Does **not** expose owner/staff identities or internal moderation fields.

---

## 7. My businesses

**`GET /api/v1/manage/businesses`** (authenticated)

Returns paginated businesses where the current user has an **active** `business_members` row (owner, manager, or staff).

Each item includes `my_role` for the authenticated user.

---

## 8. Management business details

**`GET /api/v1/manage/businesses/{business}`** (authenticated)

Requires `viewManagement` policy (active member or platform admin).

Returns `BusinessManagementResource` with:

- All public fields
- `status`, `rejection_reason`, `submitted_at`, `reviewed_at`
- `my_role`
- `booking_policy.confirmation_mode`

---

## 9. Business update

**`PATCH /api/v1/manage/businesses/{business}`** (authenticated)

Requires `update` policy (owner, manager, or platform admin).

Staff **cannot** update core business fields.

Immutable through this endpoint:

- `status` (moderation workflow — future admin prompt)
- Ownership (membership role changes use dedicated member endpoints)
- `created_by_user_id`, review metadata

Partial updates supported (`sometimes` validation).

---

## 10. Business membership management

| Method | Endpoint | Policy |
|---|---|---|
| GET | `/api/v1/manage/businesses/{business}/members` | `viewMembers` |
| POST | `/api/v1/manage/businesses/{business}/members` | `addMember` |
| PATCH | `/api/v1/manage/businesses/{business}/members/{member}` | `updateMember` |
| DELETE | `/api/v1/manage/businesses/{business}/members/{member}` | `removeMember` |

### Add member

**Body:**

```json
{
  "phone": "+998901234567",
  "member_role": "staff"
}
```

- User must already exist (identified by normalized E.164 phone)
- Duplicate active memberships rejected
- Revoked memberships are reactivated with the new role
- Database unique constraint `(business_id, user_id)` is authoritative

### Remove member

- Sets `status = revoked` (soft removal, not user account deletion)
- Cannot remove an owner
- Cannot remove the last owner (guard in `RemoveBusinessMemberAction`)

### Role changes

- Owner role cannot be changed via PATCH
- Ownership transfer is **not** supported in MVP
- Promoting to `manager` requires owner (or platform admin)

---

## 11. Working hours management

| Method | Endpoint | Authorization |
|---|---|---|
| GET | `/api/v1/manage/businesses/{business}/working-hours` | `viewManagement` |
| PUT | `/api/v1/manage/businesses/{business}/working-hours` | `manageWorkingHours` |

### Request body

```json
{
  "working_hours": [
    {
      "weekday": 1,
      "is_closed": false,
      "is_open_24h": false,
      "opens_at": "09:00",
      "closes_at": "23:00"
    }
  ]
}
```

- Exactly **7** entries required (one per weekday 1–7, ISO: Monday = 1)
- **Overnight schedules** are valid (e.g. `opens_at: 20:00`, `closes_at: 04:00`)
- Closed days: `is_closed: true`, no times
- 24h days: `is_open_24h: true`, no times
- PUT replaces the entire weekly schedule inside a transaction

Table: `business_hours` with DB constraints enforcing schedule consistency.

---

## 12. Authorization model

### Layers

1. **Route middleware:** `auth:sanctum` on protected routes
2. **Form Requests:** field validation + some authorization (e.g. role-aware `addMember`)
3. **Policies:** `BusinessPolicy` delegates to `BusinessAuthorizationService`
4. **Database constraints:** unique memberships, schedule checks

### Business-scoped checks

Authorization always verifies the authenticated user's **actual** `business_members` row for the target business. Client-provided roles are never trusted.

Platform admins (`platform_role = platform_admin`) bypass business membership for management actions except owner removal rules.

---

## 13. Permission matrix (implemented)

| Action | Owner | Manager | Staff |
|---|---|---|---|
| View public business (if approved) | Yes | Yes | Yes |
| View management data | Yes | Yes | Yes |
| Update business info | Yes | Yes | No |
| Manage working hours | Yes | Yes | No |
| View working hours (manage endpoint) | Yes | Yes | Yes |
| View members | Yes | Yes | No |
| Add staff | Yes | Yes | No |
| Add manager | Yes | No | No |
| Change member roles | Yes | Staff only* | No |
| Remove staff | Yes | Yes | No |
| Remove manager | Yes | No | No |
| Remove owner | No | No | No |

\* Managers may change staff roles only; promoting to manager or demoting managers requires owner.

---

## 14. Request validation

| Form Request | Used by |
|---|---|
| `StoreBusinessRequest` | `POST /businesses` |
| `UpdateBusinessRequest` | `PATCH /manage/businesses/{business}` |
| `StoreBusinessMemberRequest` | `POST .../members` |
| `UpdateBusinessMemberRequest` | `PATCH .../members/{member}` |
| `UpdateBusinessWorkingHoursRequest` | `PUT .../working-hours` |

Shared trait: `ValidatesBusinessFields` (coordinates pair, timezone, multilingual translations).

---

## 15. Public visibility rules

| Status | Public listing | Public details | Management access |
|---|---|---|---|
| `draft` | Hidden | Members only | Members |
| `pending_review` | Hidden | Members only | Members |
| `approved` | Visible | Visible | Members |
| `rejected` | Hidden | Members only | Members |
| `suspended` | Hidden | Members only | Members |
| `archived` | Hidden | Members only | Members |

Soft-deleted businesses are never publicly visible.

---

## 16. Multilingual dynamic content

### Categories

`business_categories.name` is JSONB: `{ "uz": "...", "kaa": "...", "ru": "..." }`.

API returns the full object plus `localized_name` resolved via `LocaleResolver::pick()` using the `Accept-Language` header (`uz`, `kaa`, `ru`).

### Businesses

Optional `translations` JSONB on `businesses` for multilingual descriptions. Returned as-is in API responses; no hardcoded PHP translations.

---

## 17. Error handling

| Situation | HTTP | Code |
|---|---|---|
| Validation failure | 422 | `validation_failed` |
| Unauthenticated | 401 | `unauthenticated` |
| Forbidden business access | 403 | `forbidden` |
| Business/member not found | 404 | `not_found` |
| Duplicate membership | 422 | field error on `phone` |

Raw database errors are not exposed to clients.

---

## 18. Example responses

### Create business (201)

```json
{
  "success": true,
  "message": "Business created successfully.",
  "data": {
    "id": "uuid",
    "name": "Cyber Arena",
    "status": "draft",
    "my_role": "owner",
    "category": { "id": "uuid", "slug": "gaming-club", "name": { "uz": "...", "ru": "..." } }
  }
}
```

### Public listing item

```json
{
  "id": "uuid",
  "name": "GameZone Nukus",
  "city": "Nukus",
  "category": { "id": "uuid", "localized_name": "O'yin klubi" }
}
```

---

## 19. Test coverage

Tests live under `tests/Feature/Api/V1/Business/`:

| File | Coverage |
|---|---|
| `BusinessCategoryTest` | Active categories, localization |
| `BusinessCreationTest` | Auth, owner membership, validation |
| `PublicBusinessTest` | Visibility, filters, pagination, search |
| `BusinessManagementTest` | My businesses, update permissions |
| `BusinessMemberTest` | Add/remove rules, duplicates, scoping |
| `BusinessWorkingHoursTest` | Schedule update, overnight, authorization |

**Note:** Tests require PostgreSQL (`PostgresTestCase`). Run locally:

```bash
php artisan test --filter=Business
```

---

## 20. Known limitations

- No geospatial proximity search (lat/lng stored but no PostGIS queries yet)
- No business status transition API (submit for review, approve — future admin prompt)
- No ownership transfer workflow
- No automatic user creation when adding members by phone
- No caching layer on public listings
- `routes/api/v1/owner.php` retained as placeholder; active routes use `/manage/`

---

## 21. Future expansion points

- Submit business for review (`pending_review`)
- Platform admin moderation API
- Resource groups and resources (Prompt #7+)
- Reservation and availability APIs
- Public "open now" derived from `business_hours` + timezone
- Favorites and reviews

---

## 22. Key files

| Type | Path |
|---|---|
| Controllers | `app/Http/Controllers/Api/V1/BusinessCategoryController.php`, `BusinessController.php`, `Manage/*` |
| Actions | `app/Actions/Businesses/*` |
| Policies | `app/Policies/BusinessPolicy.php` |
| Authorization | `app/Services/Authorization/BusinessAuthorizationService.php` |
| Resources | `app/Http/Resources/Api/V1/Business*Resource.php`, `PublicBusinessResource.php` |
| Requests | `app/Http/Requests/Api/V1/Business/*` |
| Routes | `routes/api/v1/businesses.php`, `routes/api/v1/manage.php` |
| Models | `Business`, `BusinessCategory`, `BusinessMember`, `BusinessHour`, `BookingPolicy` |
