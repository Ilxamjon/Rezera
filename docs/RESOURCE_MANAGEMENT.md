# Rezera — Resource Management API

**Status:** Implemented (Prompt #7)  
**Depends on:** `docs/BUSINESS_MANAGEMENT.md`, `docs/DATABASE-ARCHITECTURE.md`

---

## 1. Resource architecture

Rezera uses a **generic inventory model** — not PC/PlayStation-specific tables.

```
Business
  └── ResourceGroup (API: resource-category)
        └── Resource
```

| Database table | API concept | Model |
|---|---|---|
| `resource_groups` | Resource category | `ResourceGroup` |
| `resources` | Bookable unit | `Resource` |

**Architectural decision:** The approved schema (Prompt #4) uses `resource_groups`, not `resource_categories`. API routes use `/resource-categories` as the business-facing name; internally they map to `resource_groups`.

---

## 2. Resource categories (`resource_groups`)

Per-business grouping (zones/types): Gaming PC, PlayStation, VIP Room, Tables, etc.

| Field | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `business_id` | uuid | FK, cascade delete |
| `name` | string(120) | Unique per business (non-deleted) |
| `description` | text | nullable |
| `icon` | string(64) | nullable |
| `color` | string(7) | nullable, `#RRGGBB` |
| `sort_order` | integer | default 0 |
| `is_active` | boolean | default true |
| `deleted_at` | timestamp | soft delete |

---

## 3. Resource model (`resources`)

| Field | Type | Notes |
|---|---|---|
| `id` | uuid | PK |
| `business_id` | uuid | FK, restrict delete |
| `resource_group_id` | uuid | nullable, SET NULL on group delete |
| `name` | string(120) | Display name |
| `code` | string(64) | Unique per business (non-deleted) |
| `description` | text | nullable |
| `image_url` | string | nullable |
| `resource_type` | enum | `pc`, `console`, `room`, `table`, `desk`, `court`, `other` |
| `status` | enum | `active`, `inactive`, `maintenance` |
| `capacity` | integer | > 0 |
| `hourly_rate_amount` | bigint | Integer UZS (minor units) |
| `currency` | char(3) | default `UZS` |
| `rate_unit` | enum | `hour`, `day` (foundation for future pricing) |
| `metadata` | jsonb | Flexible equipment attributes |
| `sort_order` | integer | |
| `deleted_at` | timestamp | soft delete |

### API field mapping

| API field | Database column |
|---|---|
| `resource_category_id` | `resource_group_id` |
| `price` | `hourly_rate_amount` |
| `price_unit` | `rate_unit` |
| `image` | `image_url` |

---

## 4. Resource statuses

| Status | Meaning | Publicly visible | Bookable (future) |
|---|---|---|---|
| `active` | Available for booking | Yes | Yes |
| `inactive` | Disabled | No | No |
| `maintenance` | Temporarily unavailable | No | No |

DB constraint: `status = active` requires `hourly_rate_amount IS NOT NULL`.

---

## 5. Resource metadata

Stored as JSON object (`metadata` jsonb). Examples:

**Gaming PC:**
```json
{ "cpu": "Intel Core i7", "gpu": "RTX 4060", "ram": "32GB" }
```

**PlayStation:**
```json
{ "console": "PS5", "controller_count": 2, "tv_size": "55 inch" }
```

**VIP Room:**
```json
{ "capacity": 8, "features": ["AC", "Sofa", "PS5"] }
```

No per-attribute columns — new resource types need no schema changes.

---

## 6. Pricing foundation

- **Amount:** `hourly_rate_amount` — integer UZS (no floats)
- **Unit:** `rate_unit` — `hour` (default) or `day`
- **Currency:** `currency` — `UZS`

API exposes `price` + `price_unit` for Flutter clients.

**Not implemented:** peak hours, weekend rates, coupons, membership discounts, dynamic pricing.

**Future extension points:**
- `price_rules` table (referenced in architecture as non-MVP)
- `resource_unavailability` for blocked periods
- Rate snapshots on `reservations` (already in schema)

---

## 7. Management endpoints

All require `auth:sanctum` and business membership.

### Resource categories

| Method | Endpoint |
|---|---|
| GET | `/api/v1/manage/businesses/{business}/resource-categories` |
| POST | `/api/v1/manage/businesses/{business}/resource-categories` |
| GET | `/api/v1/manage/businesses/{business}/resource-categories/{category}` |
| PATCH | `/api/v1/manage/businesses/{business}/resource-categories/{category}` |
| DELETE | `/api/v1/manage/businesses/{business}/resource-categories/{category}` |

**Delete rule:** Blocked if category still has non-deleted resources.

### Resources

| Method | Endpoint |
|---|---|
| GET | `/api/v1/manage/businesses/{business}/resources` |
| POST | `/api/v1/manage/businesses/{business}/resources` |
| GET | `/api/v1/manage/businesses/{business}/resources/{resource}` |
| PATCH | `/api/v1/manage/businesses/{business}/resources/{resource}` |
| DELETE | `/api/v1/manage/businesses/{business}/resources/{resource}` |

**Delete:** Soft delete (`deleted_at`). Historical reservations remain linked via `resource_id`.

### List filters (management)

| Parameter | Description |
|---|---|
| `resource_category_id` | Filter by category |
| `status` | `active`, `inactive`, `maintenance` |
| `is_active` | `true` → active, `false` → inactive |
| `search` | Name or code partial match |
| `sort` | `sort_order` (default), `name`, `code`, `status` |
| `direction` | `asc` (default), `desc` |
| `per_page` | 1–100, default 50 |

---

## 8. Public endpoints

**`GET /api/v1/businesses/{business}/resources`**

- Business must be publicly visible (`approved`) OR caller is a member (preview)
- Returns only **`active`** resources
- Uses `PublicResourceResource` — no `business_id`, audit fields
- Filters: `resource_category_id`, `resource_type`, `per_page`

Business details (`GET /businesses/{business}`) does **not** embed resources — use the dedicated endpoint to avoid large payloads.

---

## 9. Authorization

| Action | Owner | Manager | Staff | Customer |
|---|---|---|---|---|
| View resources (manage) | Yes | Yes | Yes | No |
| Create/update/delete resources | Yes | Yes | No | No |
| View resource categories (manage) | Yes | Yes | Yes | No |
| Manage categories | Yes | Yes | No | No |
| Public resource list | Yes* | Yes* | Yes* | Yes** |

\* When business is approved, or member previewing own business  
\*\* Approved businesses only

Policies: `ResourcePolicy`, `ResourceGroupPolicy` via `BusinessAuthorizationService`.

---

## 10. Validation rules

- `resource_category_id` must belong to the **same business** (critical multi-tenant rule)
- `code` unique per business (non-deleted)
- `name` unique per category per business (non-deleted groups)
- `price` required for `active` resources
- `metadata` must be JSON object
- `capacity` > 0
- `color` must match `#RRGGBB` if provided

---

## 11. Multi-tenant security

Every write validates:

1. User has membership in target business
2. `resource_category_id` exists in `resource_groups` where `business_id` matches route business
3. Route `{resource}` / `{category}` belongs to route `{business}` (404 if mismatch)

Never trust client-supplied business or category IDs alone.

---

## 12. Soft deletion

| Entity | Behavior |
|---|---|
| Resource | `deleted_at` set; row retained for reservation history |
| Resource group | Soft delete; blocked if active resources exist |

---

## 13. Future availability integration

The availability engine (next prompt) will combine:

```
Resource.status (active/inactive/maintenance)
+ business_hours (working window)
+ reservations (occupancy_range EXCLUDE constraint)
+ resource_unavailability (future)
+ booking_policies (buffers, min duration)
```

Resource Management provides clean, queryable data:

- `scopePubliclyBookable()` — active, non-deleted
- `ResourceStatus::isBookable()` — `active` only
- Integer pricing for rate snapshots on reservations
- `metadata` for display, not availability logic

---

## 14. Future reservation integration

Existing schema already includes:

- `reservations.resource_id` → `resources.id` (RESTRICT)
- Composite FK `(resource_id, business_id)` on reservations
- `hourly_rate_amount` denormalized on reservations
- PostgreSQL `tstzrange` EXCLUDE for overlap prevention

Soft-deleted resources remain referencable for historical bookings.

---

## 15. Example requests

### Create category

```http
POST /api/v1/manage/businesses/{id}/resource-categories
Authorization: Bearer {token}

{
  "name": "Gaming PC",
  "description": "High-end gaming stations",
  "icon": "pc",
  "color": "#3366FF"
}
```

### Create resource

```http
POST /api/v1/manage/businesses/{id}/resources

{
  "resource_category_id": "{category-uuid}",
  "name": "PC #01",
  "code": "PC-001",
  "resource_type": "pc",
  "price": 15000,
  "price_unit": "hour",
  "metadata": { "gpu": "RTX 4060", "ram": "32GB" }
}
```

### Public list response item

```json
{
  "id": "uuid",
  "name": "PC #01",
  "code": "PC-001",
  "price": 15000,
  "price_unit": "hour",
  "status": "active",
  "category": { "id": "uuid", "name": "Gaming PC" },
  "metadata": { "gpu": "RTX 4060" }
}
```

---

## 16. Tests

`tests/Feature/Api/V1/Resource/`:

- `ResourceCategoryTest`
- `ResourceManagementTest`
- `PublicResourceTest`

Run: `php artisan test --filter=Resource`

---

## 17. Key files

| Type | Path |
|---|---|
| Migration | `database/migrations/2026_09_02_000001_extend_resource_inventory_tables.php` |
| Models | `app/Models/Resource.php`, `ResourceGroup.php` |
| Enums | `ResourceStatus`, `ResourceType`, `RateUnit` |
| Policies | `ResourcePolicy`, `ResourceGroupPolicy` |
| Actions | `app/Actions/Resources/*` |
| Controllers | `Manage/ResourceController`, `Manage/ResourceCategoryController`, `PublicResourceController` |
| Routes | `routes/api/v1/manage.php`, `routes/api/v1/businesses.php` |
