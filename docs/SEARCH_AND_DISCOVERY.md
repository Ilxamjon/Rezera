# Search, Discovery & Filtering Engine

Customer-facing business discovery for the Rezera mobile application.

## Status legend

| Symbol | Meaning |
|---|---|
| ✅ | Implemented |
| 🔜 | Planned |

---

## 1. Architecture

```
Flutter
  ↓
GET /api/v1/businesses
  ↓
BusinessDiscoveryRequest (validation)
  ↓
BusinessDiscoveryFilters (DTO)
  ↓
BusinessDiscoveryService
  ↓
BusinessSearchProviderInterface
  ↓
DatabaseBusinessSearchProvider
  ↓
BusinessDiscoveryQueryBuilder
  ├── text search
  ├── location / geo filters
  ├── price filters
  ├── BusinessOpenNowFilter
  ├── BusinessDiscoveryAvailabilityFilter
  └── safe sorting
  ↓
BusinessDiscoveryResource (list) / PublicBusinessResource (detail)
```

Availability correctness for booking still uses **`AvailabilityEngine`** on per-business endpoints. Discovery availability filters use SQL `EXISTS` checks aligned with the same occupying reservation statuses.

Future search engines can replace `DatabaseBusinessSearchProvider` without changing the public API contract.

---

## 2. Discovery endpoint

### `GET /api/v1/businesses` ✅

Extended public discovery endpoint. Returns paginated business cards.

**Default pagination:** `per_page=20`, maximum `50`.

**Default sort:**

| Condition | Default sort |
|---|---|
| Text search (`q` / `search`) | `relevance` |
| Geo coordinates provided | `nearest` |
| Otherwise | `newest` (`created_at` desc) |

---

## 3. Supported query parameters

| Parameter | Type | Description | Status |
|---|---|---|---|
| `q` | string | Primary search query (preferred) | ✅ |
| `search` | string | Legacy alias for `q` | ✅ |
| `category_id` | uuid | Business category ID | ✅ |
| `category` | string | Business category slug | ✅ |
| `city` | string | Case-insensitive city match | ✅ |
| `district` | string | Case-insensitive district match | ✅ |
| `region` | string | Case-insensitive region match | ✅ |
| `country_code` | string | ISO country code (2 letters) | ✅ |
| `latitude` | float | Geo search latitude | ✅ |
| `longitude` | float | Geo search longitude | ✅ |
| `radius` | float | Radius in km (0.1–100, default 10) | ✅ |
| `open_now` | bool | Only businesses open right now (business timezone) | ✅ |
| `available_now` | bool | Open + at least one unbooked active resource now | ✅ |
| `available_date` | date | Availability window date (`Y-m-d`) | ✅ |
| `available_start_time` | time | Availability window start (`H:i`) | ✅ |
| `available_end_time` | time | Availability window end (`H:i`) | ✅ |
| `resource_category_id` | uuid | Filter businesses with active resources in group | ✅ |
| `resource_type` | enum | `pc`, `console`, `room`, `table`, etc. | ✅ |
| `min_price` | int | Minimum starting hourly price (UZS) | ✅ |
| `max_price` | int | Maximum starting hourly price (UZS) | ✅ |
| `sort` | string | See sorting section | ✅ |
| `include_open_now` | bool | Include `open_now` flag in list items | ✅ |
| `page` | int | Page number | ✅ |
| `per_page` | int | Page size (1–50) | ✅ |

Invalid parameters return **422**.

---

## 4. Search behavior ✅

- Case-insensitive (`ILIKE` on PostgreSQL)
- Whitespace normalized (`"  gaming club  "` → `"gaming club"`)
- Searches: `name`, `description`, `address_line`, `city`, `district`, `region`, `translations` JSON text
- Maximum query length: **120 characters**
- Parameterized queries (SQL injection safe)

### Multi-language foundation ✅

Localized business content is stored in `translations` JSON. Search includes JSON text and can be extended to language-specific fields without API changes.

---

## 5. Category discovery

### `GET /api/v1/business-categories` ✅

Returns active categories with:

| Field | Description |
|---|---|
| `id`, `slug`, `name` | Identity |
| `localized_name`, `localized_description` | Resolved from `Accept-Language` |
| `description`, `icon`, `image_url` | Display metadata |
| `business_count` | Count of publicly visible businesses |
| `sort_order` | Display order |

Inactive categories are excluded. Businesses in inactive categories are excluded from public discovery via category relationship checks.

---

## 6. Location & geo search ✅

**City / district / region / country** filters use case-insensitive matching.

**Geo search** (`latitude`, `longitude`, `radius`):

1. Bounding-box prefilter
2. Haversine distance in SQL
3. `distance_km` returned in list items when geo search is used

Radius defaults to **10 km**, maximum **100 km**.

---

## 7. Sorting ✅

Whitelist only — raw request values are never injected into `ORDER BY`.

| Sort | Behavior |
|---|---|
| `relevance` | Name prefix match first, then name |
| `newest` | `created_at` descending |
| `name` | Alphabetical |
| `nearest` | `distance_km` ascending (requires coordinates) |
| `price_low` | Lowest `price_from` first |
| `price_high` | Highest `price_from` first |
| `popular` | Reservation count in last 30 days (confirmed/completed/checked-in) |

---

## 8. `open_now` ✅

Uses business-local timezone via PostgreSQL `timezone(businesses.timezone, now())`.

Supports:

- Same-day hours
- Overnight hours (e.g. 18:00 → 02:00)
- 24-hour days
- Spillover from previous calendar day

When `open_now=true`, results include `open_now: true` on each item.

---

## 9. Availability filtering ✅

### Date/time window

`available_date` + `available_start_time` + `available_end_time` must be provided together.

- Interval interpreted in **each business's timezone**
- Overnight intervals supported (e.g. `22:00` → `01:00`)
- Past intervals rejected with **422**
- Businesses qualify when at least one **active** resource has no occupying reservation conflict

### `available_now`

Business has at least one active resource not occupied by a reservation at the current UTC moment.

### Working hours on discovery availability

Date/time availability SQL checks reservation conflicts. Full working-hours validation for a specific slot remains on:

- `GET /api/v1/businesses/{business}/availability`

This keeps discovery performant while preserving `AvailabilityEngine` as the booking source of truth.

---

## 10. Pricing filter ✅

`price_from` = **minimum `hourly_rate_amount`** among active resources for the business.

`min_price` / `max_price` filter on this starting price. Currency: platform default (`UZS`).

---

## 11. List vs detail responses

### List (`BusinessDiscoveryResource`) ✅

Optimized mobile card: identity, location summary, category, `price_from`, optional `distance_km`, optional `open_now`.

Does **not** expose: status, members, internal settings, full resource lists.

### Detail (`GET /api/v1/businesses/{business}`) ✅

Enhanced `PublicBusinessResource` with:

- `resource_categories`
- `resources` (publicly bookable)
- `price_from`, `open_now`
- `working_hours`

---

## 12. Public visibility & security ✅

Only businesses matching **all** of:

- `status = approved`
- `is_publicly_listed = true`
- not soft-deleted
- active business category

Suspended, draft, private, and deleted businesses are hidden from discovery.

---

## 13. Performance ✅

- SQL `COUNT`, `MIN`, `EXISTS` subqueries
- Eager-loaded categories
- Geo bounding-box prefilter before Haversine
- Indexed columns for discovery filters
- No full-table loads into PHP

### Indexes added

- `businesses_discovery_visibility_idx`
- `businesses_district_idx`
- `businesses_coordinates_idx`
- `resources_discovery_idx`

---

## 14. Search provider abstraction ✅

```php
interface BusinessSearchProviderInterface {
    public function search(BusinessDiscoveryFilters $filters): LengthAwarePaginator;
}
```

| Provider | Status |
|---|---|
| `DatabaseBusinessSearchProvider` | ✅ Implemented |
| `MeilisearchBusinessSearchProvider` | 🔜 Planned |
| `ElasticBusinessSearchProvider` | 🔜 Planned |

Configure via `REZERA_SEARCH_PROVIDER=database` in `.env`.

---

## 15. Not implemented in this stage

| Feature | Status |
|---|---|
| Elasticsearch / Algolia / Meilisearch | 🔜 |
| AI / semantic search | 🔜 |
| Typo correction | 🔜 |
| Recommendation engine | 🔜 |
| Featured / promoted businesses | 🔜 |
| Review / rating system | 🔜 |
| Aggressive search caching | 🔜 |
| Map UI | 🔜 (mobile) |

---

## 16. Configuration

`config/rezera.php` → `discovery`:

| Key | Default |
|---|---|
| `default_radius_km` | 10 |
| `max_radius_km` | 100 |
| `default_per_page` | 20 |
| `max_per_page` | 50 |
| `popularity_days` | 30 |
| `search_provider` | `database` |

---

## 17. Testing

Run:

```bash
php artisan test --filter=BusinessDiscovery
php artisan test --filter=PublicBusiness
php artisan test --filter=BusinessCategory
```

Covers search, filters, geo, sorting, visibility, availability, pricing, pagination, and N+1 guards.
