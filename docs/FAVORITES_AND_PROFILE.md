# Favorites, Saved Businesses & User Profile

Customer profile management and business favorites foundation.

> **Full favorites architecture:** [FAVORITES_AND_WISHLIST.md](./FAVORITES_AND_WISHLIST.md)

## Status legend

| Symbol | Meaning |
|---|---|
| ✅ | Implemented |
| 🔜 | Planned |

---

## 1. Profile

### Endpoints ✅

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/api/v1/me/profile` | Yes | Primary customer profile endpoint |
| PATCH | `/api/v1/me/profile` | Yes | Update allowed fields |
| GET | `/api/v1/profile` | Yes | Legacy alias (backward compatible) |
| PATCH | `/api/v1/profile` | Yes | Legacy alias |

### Response fields ✅

`id`, `name`, `phone`, `email`, `avatar_url`, `locale`, `preferred_language` (alias), `timezone`, `phone_verified_at`, `email_verified_at`, `created_at`, `updated_at`

Does **not** expose: `password`, tokens, `platform_role`, `status`, internal admin metadata.

### Updatable fields ✅

| Field | Notes |
|---|---|
| `name` | max 120 |
| `locale` / `preferred_language` | `uz`, `kaa`, `ru` |
| `timezone` | IANA identifier (e.g. `Asia/Tashkent`) |
| `email` | Validated; changing email clears `email_verified_at` |
| `avatar_url` | URL string |

### Protected fields ✅

Cannot be changed via profile PATCH:

- `phone` (requires separate verification flow)
- `status`, `platform_role`, `password`

### Phone change 🔜

Planned endpoint (not implemented):

```
POST /api/v1/me/profile/change-phone
```

Requires SMS verification integration.

### User timezone vs business timezone

- **User `timezone`**: display/preference for the customer app
- **Business `timezone`**: authoritative for availability and reservations

Notification preferences remain in `/api/v1/me/notification-preferences` (Prompt #12).

---

## 2. Favorites

### Table: `business_favorites` ✅

| Column | Type |
|---|---|
| `id` | uuid PK |
| `user_id` | uuid FK → users (cascade delete) |
| `business_id` | uuid FK → businesses (cascade delete) |
| `created_at` | timestamp |

**Constraints:**

- `UNIQUE(user_id, business_id)`
- `INDEX(user_id, created_at)`
- `INDEX(business_id)`

### Rules ✅

A user may favorite a business only when:

- business exists
- `isPubliclyVisible()` is true (approved, publicly listed, not deleted)

Favorite/unfavorite operations are idempotent.

When a business is soft-deleted or hidden:

- favorite rows may remain in DB
- public favorite lists exclude non-visible businesses
- favorite state is restored when business becomes visible again

### Endpoints ✅

| Method | Path | Description |
|---|---|---|
| GET | `/api/v1/me/favorites/businesses` | Paginated favorite list (newest first) |
| POST | `/api/v1/me/favorites/businesses/{business}` | Add favorite |
| DELETE | `/api/v1/me/favorites/businesses/{business}` | Remove favorite |

### Favorite action response ✅

```json
{
  "success": true,
  "data": {
    "business_id": "uuid",
    "is_favorite": true
  }
}
```

---

## 3. Discovery integration ✅

### `GET /api/v1/businesses`

| Auth | `is_favorite` behavior |
|---|---|
| Guest | Field omitted |
| Authenticated | `true` / `false` per business |

Implemented with a single `withExists` subquery — **no N+1**.

### Optional filter ✅

```
GET /api/v1/businesses?favorites=true
```

Requires authentication. Returns only the authenticated user's publicly visible favorites.

### `GET /api/v1/businesses/{business}` ✅

Includes `is_favorite` for authenticated users only.

### Favorite count 🔜

Public `favorite_count` is not exposed yet. Architecture supports adding aggregate counts later without API breaking changes.

---

## 4. Authorization & privacy ✅

- Favorites are private per user
- `user_id` is never accepted from the client
- No public `GET /users/{id}` endpoint
- Business staff cannot browse customer favorites
- IDOR protections tested

---

## 5. Services

| Class | Responsibility |
|---|---|
| `UpdateUserProfileAction` | Safe profile updates |
| `FavoriteBusinessService` | Favorite/unfavorite with visibility checks |
| `FavoriteBusinessListService` | Paginated favorite business list |

---

## 6. Performance

- Discovery: `withExists(['favorites as is_favorite' => ...])` — O(1) extra query
- Favorite list: single join query with pagination
- Unique DB constraint prevents duplicate favorites under concurrency

---

## 7. Future extension points 🔜

- Phone change with SMS verification
- Public favorite counts
- Recently viewed businesses
- Personalized discovery / recommendations
- Milestone notifications for business owners

---

## 8. Testing

```bash
php artisan migrate
php artisan test --filter=ProfileAndFavorites
php artisan test --filter=Profile
```

---

## 9. Not implemented

- AI recommendations
- Social following
- Public user profiles
- Reviews/ratings
- Loyalty/referral systems
- SMS phone change flow
- Push notifications on favorite actions
