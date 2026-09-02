# Saved Searches & Smart Availability Alerts

Production-ready saved search and availability alert foundation for Rezera customers.

## Purpose

Authenticated users can save reusable discovery/availability filter configurations and optionally receive notifications when a matching booking opportunity becomes available.

Saved searches are **not** a second search engine. They store criteria and invoke:

```text
BusinessDiscoveryQueryBuilder → AvailabilityEngine → NotificationService
```

## Architecture

| Component | Responsibility |
|---|---|
| `user_saved_searches` | Private saved filter configuration per user |
| `saved_search_alerts` | Alert delivery deduplication ledger |
| `SavedSearchCriteria` | Translates DB state → discovery filters + time windows |
| `SavedSearchMatcher` | Candidate narrowing + AvailabilityEngine checks |
| `SavedSearchAlertService` | Match deduplication + notifications |
| `EvaluateSavedSearchJob` | Queue-safe per-search evaluation |
| `ProcessSavedSearchAlertsCommand` | Scheduled batch dispatcher |

## Database

### `user_saved_searches`

Stores filter fields aligned with Search & Discovery (`search_query`, `category_id`, location, `business_id`, resource filters, price, date/time modes, alert settings).

Indexes: `user_id`, `(user_id, alert_enabled)`, `business_id`, `resource_id`, `last_checked_at`.

### `saved_search_alerts`

| Column | Purpose |
|---|---|
| `match_hash` | Deterministic identity for a slot |
| `notification_id` | Link to `user_notifications` |
| `status` | `sent` / `failed` |

Unique: `(saved_search_id, match_hash)` — prevents duplicate alerts under concurrency.

## Date modes

| Mode | Behavior |
|---|---|
| `specific_date` | Single future date |
| `next_available` | Scan next N days (configurable lookahead) |
| `recurring` | Matching weekdays in lookahead window |
| `date_range` | Each day from `date_from` → `date_to` |

Availability matching uses each business's timezone via `AvailabilityEngine`.

## API (authenticated)

| Method | Path |
|---|---|
| GET | `/api/v1/me/saved-searches` |
| POST | `/api/v1/me/saved-searches` |
| GET | `/api/v1/me/saved-searches/{savedSearch}` |
| PATCH | `/api/v1/me/saved-searches/{savedSearch}` |
| DELETE | `/api/v1/me/saved-searches/{savedSearch}` |

POST/PATCH are throttled (`saved-searches` limiter). Max saved searches per user: `config('rezera.saved_searches.max_per_user')` (default 25).

Alert enable/disable is handled via PATCH (`alert_enabled`) — no separate toggle endpoints.

## Matching flow

```text
User creates SavedSearch
        ↓
Scheduler (every 5 min) OR reservation cancelled event
        ↓
EvaluateSavedSearchJob
        ↓
SavedSearchMatcher
  • narrow businesses (specific business_id or discovery query)
  • resolve date/time windows
  • AvailabilityEngine per business/resource
        ↓
SavedSearchAlertService
  • insert saved_search_alerts (unique match_hash)
  • NotificationService (type: availability_alert)
        ↓
User may book via normal reservation flow
```

## Event-driven optimization

`ReservationStatusChanged` → `Cancelled` triggers targeted re-evaluation for saved searches scoped to that `business_id` or `resource_id`.

Scheduled polling remains the fallback for broad searches.

## Notifications

- Type: `availability_alert`
- Default channel: in-app (`database`)
- Optional: `push` when configured
- Localized: `uz`, `kaa`, `ru`, `en`
- Payload includes `deep_link` for mobile navigation
- Message semantics: opportunity available — **not** a confirmed reservation

## Privacy

- Saved searches are private per user
- Business staff cannot read customer saved searches
- Admin sees aggregates only (platform analytics overview)

## Configuration

```php
config('rezera.saved_searches.max_per_user')          // default 25
config('rezera.saved_searches.lookahead_days')        // default 14
config('rezera.saved_searches.batch_size')            // default 50
config('rezera.saved_searches.scheduler_minutes')     // default 5
config('rezera.saved_searches.alert_retention_days')  // default 90
```

## Commands

```bash
php artisan saved-searches:process-alerts
php artisan saved-searches:cleanup-alerts
```

## Testing

```bash
php artisan migrate
php artisan test --filter=SavedSearch
```

## Future extension

- Price-drop alerts
- Favorite-business scoped alerts
- User-defined collections
- Personalized recommendations
- Saved search sharing (explicit product decision required)
