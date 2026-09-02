# Reviews & Ratings

> **See also:** [REVIEWS_AND_REPUTATION.md](./REVIEWS_AND_REPUTATION.md) for the full Prompt #22 architecture (reports, moderation queues, rating rebuild, analytics integration).

Production-ready review foundation for Rezera businesses.

## Eligibility

Only **`completed`** reservations may be reviewed.

| Reservation status | Can review? |
|---|---|
| `completed` | Yes |
| `pending`, `confirmed`, `checked_in` | No |
| `cancelled`, `rejected`, `expired`, `no_show` | No |

Rules:
- One review per reservation (`UNIQUE(reservation_id)`)
- Reservation must belong to authenticated user
- Reservation must belong to requested business
- Favorites do **not** grant review eligibility

## Data model

Table: `reviews`

| Field | Notes |
|---|---|
| `business_id`, `user_id`, `reservation_id` | Required relationships |
| `rating` | Integer 1–5 |
| `title` | Optional, max 150 |
| `body` | Optional, max 2000 |
| `status` | `published`, `hidden`, `pending` |
| `business_response` | Single active response |
| Moderation | `hidden_at`, `hidden_by`, `hidden_reason` |
| Soft deletes | Yes |

## API endpoints

### Public / customer

| Method | Path | Auth |
|---|---|---|
| GET | `/api/v1/businesses/{business}/reviews` | No |
| POST | `/api/v1/businesses/{business}/reviews` | Yes |
| GET | `/api/v1/me/reviews` | Yes |
| PATCH | `/api/v1/me/reviews/{review}` | Yes (author) |
| DELETE | `/api/v1/me/reviews/{review}` | Yes (author) |

### Business management

| Method | Path | Auth |
|---|---|---|
| GET | `/api/v1/manage/businesses/{business}/reviews` | Staff+ |
| GET | `/api/v1/manage/businesses/{business}/reviews/{review}` | Staff+ |
| POST/PATCH | `.../reviews/{review}/response` | Owner/Manager |
| DELETE | `.../reviews/{review}/response` | Owner/Manager |

### Platform admin

| Method | Path |
|---|---|
| GET | `/api/v1/admin/reviews` |
| GET | `/api/v1/admin/reviews/{review}` |
| PATCH | `/api/v1/admin/reviews/{review}/hide` |
| PATCH | `/api/v1/admin/reviews/{review}/restore` |

## Rating aggregates

Published, non-deleted reviews only.

Discovery and business detail expose:

```json
"rating": {
  "average": 4.7,
  "count": 128
}
```

Calculated via SQL subqueries — no N+1, no full-table PHP aggregation.

Business detail can also compute distribution internally via `ReviewRatingService`.

## Authorization

| Actor | Permissions |
|---|---|
| Customer | Create (eligible), view own, update/delete own |
| Business staff | View business reviews |
| Owner/Manager | Respond to reviews |
| Platform admin | View all, hide, restore |

## Notifications

| Event | Recipient |
|---|---|
| `review_created` | Business booking managers |
| `review_response_received` | Review author |

Uses existing `NotificationService` (Prompt #12).

## Audit log

Admin hide/restore writes to `audit_logs` via `CreateAuditLogAction`.

## Privacy

Public reviews expose limited author info: `id`, `name`, `avatar_url` only.

## Testing

```bash
php artisan migrate
php artisan test --filter=ReviewApi
```

## Not implemented

- AI moderation
- Fractional ratings
- Multiple responses per review
- Public favorite counts from reviews
- Review-based discovery sort redesign
