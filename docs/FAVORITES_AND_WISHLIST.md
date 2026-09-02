# Favorites & Wishlist (Saved Businesses)

Production-ready favorites foundation for Rezera customers.

> **Related:** [FAVORITES_AND_PROFILE.md](./FAVORITES_AND_PROFILE.md) covers profile endpoints alongside favorites.

## Purpose

Authenticated users can save businesses they are interested in for easier access later.

A favorite is **not** a review, rating, social like, popularity vote, recommendation, or loyalty action.

## Architecture

| Component | Responsibility |
|---|---|
| `business_favorites` table | Authoritative user ↔ business relationship |
| `BusinessFavorite` model | Pivot record |
| `FavoriteBusinessService` | Add/remove/check with visibility rules |
| `AddFavoriteBusinessAction` / `RemoveFavoriteBusinessAction` | Thin domain actions + events |
| `FavoriteBusinessListService` | Paginated favorite business list |
| `FavoriteAnalyticsService` | Business-level favorite metrics |

## Database schema

Table: `business_favorites`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `user_id` | uuid FK → users | cascade delete |
| `business_id` | uuid FK → businesses | cascade delete |
| `created_at` | timestamp | when saved |

**Constraints:**

- `UNIQUE(user_id, business_id)` — mandatory; prevents duplicates under concurrency

**Indexes:**

- `(user_id, created_at)` — user favorite list (newest first)
- `(business_id)` — business-level lookups
- `(business_id, created_at)` — analytics / period aggregation

No soft deletes on the pivot. Removing a favorite deletes the row.

## Relationships

**User:**

```php
$user->favoriteBusinesses(); // BelongsToMany
$user->favorites();          // HasMany BusinessFavorite
```

**Business:**

```php
$business->favoritedByUsers(); // BelongsToMany
$business->favorites();        // HasMany BusinessFavorite
```

## API endpoints

All `/me/favorites/*` routes require `auth:sanctum`.

| Method | Path | Description |
|---|---|---|
| GET | `/api/v1/me/favorites/businesses` | Paginated saved businesses (newest first) |
| POST | `/api/v1/me/favorites/businesses/{business}` | Add favorite (idempotent) |
| DELETE | `/api/v1/me/favorites/businesses/{business}` | Remove favorite (idempotent) |

### Add / remove response

```json
{
  "success": true,
  "data": {
    "business_id": "uuid",
    "is_favorite": true
  }
}
```

No toggle endpoint — explicit POST/DELETE semantics are preferred for reliable mobile clients.

### List response

Uses `BusinessDiscoveryResource` with `is_favorite: true`, category, location, price, rating summary, and pagination meta.

Query params: `page`, `per_page` (max 50).

## Business visibility

A business is favoritable only when `Business::isPubliclyVisible()`:

- approved status
- publicly listed
- not soft-deleted

Hidden, suspended, or deleted businesses return **404** on favorite attempts.

## Discovery integration

### `GET /api/v1/businesses`

| Auth | `is_favorite` |
|---|---|
| Guest | Field omitted |
| Authenticated | `true` / `false` per item |

Implemented via a single `withExists` subquery — **no N+1**.

### Filter

```
GET /api/v1/businesses?favorites=true
```

Authenticated only. Returns the user's publicly visible favorites via discovery infrastructure. Canonical list remains `GET /me/favorites/businesses`.

### Business detail

`GET /api/v1/businesses/{business}` includes `is_favorite` for authenticated users.

### Favorite count (public)

Public `favorite_count` is **not** exposed in discovery/detail responses to avoid unintended popularity ranking. Aggregate counts are available to:

- Business analytics (`/manage/businesses/{business}/analytics/favorites`)
- Platform admin analytics overview
- Admin business resources (`favorites_count`)

## Authentication & authorization

- `user_id` is never accepted from the client
- Favorites are private per user
- Business staff cannot browse customer favorite lists
- No cross-user IDOR paths

## Idempotency

| Operation | Repeated calls |
|---|---|
| POST favorite | One row; always returns `is_favorite: true` |
| DELETE favorite | Safe when not favorited; returns `is_favorite: false` |

## Concurrency

Duplicate concurrent POST requests are handled by the database unique constraint. `FavoriteBusinessService` catches `UniqueConstraintViolationException` and treats it as success.

## Privacy

- Favorite lists are visible only to the owning user
- Individual favoriting users are not exposed to business owners
- Only aggregate counts appear in admin/analytics contexts

## Analytics integration

**Business overview** includes:

```json
"favorites": {
  "total_favorites": 154,
  "favorites_added": 12
}
```

**Dedicated endpoint:**

```
GET /api/v1/manage/businesses/{business}/analytics/favorites
```

**Platform admin overview** includes total favorite relationships and period additions.

## Events (extension points)

| Event | When |
|---|---|
| `BusinessFavorited` | New favorite created |
| `BusinessUnfavorited` | Favorite removed |

No notifications are sent by default. Future consumers: recommendations, personalized discovery, milestone analytics.

## Reviews, reservations, loyalty

- Favorite status coexists with rating/review data in `BusinessDiscoveryResource`
- Reservations do not auto-favorite businesses
- Cancellation does not remove favorites
- No loyalty points for favoriting

## Performance

- Discovery: O(1) extra query via `withExists`
- Favorite list: single join + pagination + cached rating columns
- No user-specific caching (favorite state changes frequently)

## Testing

```bash
php artisan migrate
php artisan test --filter=ProfileAndFavorites
php artisan test --filter=FavoriteAnalytics
```

## Future extension: collections / wishlists

The current `business_favorites` table maps cleanly to a future `favorite_collections` model:

```
User → Collection → CollectionItem → Business
```

Saved searches, "want to visit", and followed businesses can build on the same user-scoped relationship pattern without breaking the current API.

## Not implemented

- Toggle endpoint
- Public favorite counts in discovery
- Push notifications on favorite/unfavorite
- AI recommendations
- Social following
- User-created collections
