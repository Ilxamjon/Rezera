# Reviews, Ratings & Business Reputation

Production-ready review and reputation engine for Rezera businesses.

## Architecture

Reviews are tied to **completed reservations**. The server derives `user_id`, `business_id`, and `resource_id` from authentication and reservation ownership — clients cannot submit fake reviews with arbitrary IDs.

Core components:

| Component | Responsibility |
|---|---|
| `Review` model | Authoritative review records |
| `ReviewEligibilityService` | Reservation-based eligibility |
| `RatingSummaryService` | Single source of truth for aggregates |
| `ReviewModerationTransitionService` | Valid moderation state transitions |
| `ReviewQueryBuilder` | Public, management, and admin queries |
| Actions | Create, update, delete, respond, report, moderate |

Cached columns on `businesses` (`rating_average`, `rating_count`) improve discovery performance. Review rows remain authoritative; use `php artisan reviews:recalculate-ratings` to rebuild.

## Eligibility

Only **`completed`** reservations may be reviewed.

| Reservation status | Can review? |
|---|---|
| `completed` | Yes |
| `pending`, `confirmed`, `checked_in` | No |
| `cancelled`, `rejected`, `expired`, `no_show` | No |

Rules:

- Authenticated customer only
- Reservation must belong to the customer
- Reservation must belong to the business
- One review per reservation (`UNIQUE(reservation_id)`)
- `resource_id` is copied from the reservation server-side

## Rating scale

- Integer **1–5** only (client-submitted)
- Averages may be decimal (e.g. `4.25`)
- Distribution counts per star rating

Only **`published`** and non-deleted reviews contribute to public aggregates.

## Review lifecycle

| Status | Public visibility | Contributes to rating |
|---|---|---|
| `pending` | No | No |
| `published` | Yes | Yes |
| `hidden` | No | No |
| `rejected` | No | No |
| soft-deleted | No | No |

Valid moderation transitions:

```
pending → published | rejected
published → hidden | rejected
hidden → published
rejected → published
```

Admin soft-delete removes a review from public aggregates while preserving audit history.

## API endpoints

### Customer

| Method | Path | Notes |
|---|---|---|
| POST | `/api/v1/me/reservations/{reservation}/review` | Preferred creation route |
| POST | `/api/v1/businesses/{business}/reviews` | Legacy; requires `reservation_id` in body |
| GET | `/api/v1/me/reviews` | Customer history |
| PATCH | `/api/v1/me/reviews/{review}` | Author edit |
| DELETE | `/api/v1/me/reviews/{review}` | Soft delete |
| POST | `/api/v1/reviews/{review}/report` | Report inappropriate content |

### Public

| Method | Path | Notes |
|---|---|---|
| GET | `/api/v1/businesses/{business}/reviews` | Published only; supports `sort`, `rating`, pagination; includes rating summary |

Sort values: `newest` (default), `oldest`, `highest`, `lowest`.

### Business management

| Method | Path | Auth |
|---|---|---|
| GET | `/api/v1/manage/businesses/{business}/reviews` | Staff+ |
| POST/PATCH/DELETE | `.../reviews/{review}/response` | Owner/Manager |
| GET | `/api/v1/manage/businesses/{business}/analytics/reviews` | Staff+ |

Management filters: `status`, `rating`, `search`, `resource_id`, `has_response`, `reported`, `date_from`, `date_to`.

### Platform admin

| Method | Path |
|---|---|
| GET | `/api/v1/admin/reviews` |
| GET | `/api/v1/admin/reviews/{review}` |
| PATCH | `/api/v1/admin/reviews/{review}/status` |
| PATCH | `/api/v1/admin/reviews/{review}/hide` | Backward compatible |
| PATCH | `/api/v1/admin/reviews/{review}/restore` | Backward compatible |

Admin `status` actions: `publish`, `hide`, `reject`, `restore`, `delete`.

Admin queues: `queue=pending|reported|recent|low_rating`.

## Rating aggregation

`RatingSummaryService::summaryForBusiness()` calculates:

```json
{
  "average": 4.6,
  "count": 127,
  "distribution": { "1": 2, "2": 3, "3": 7, "4": 25, "5": 90 }
}
```

Distribution sum always equals `count`.

Rebuild command:

```bash
php artisan reviews:recalculate-ratings
php artisan reviews:recalculate-ratings {business_id}
```

## Business responses

Single inline response per review (`business_response` on `reviews`). Only owner/manager may respond. Customers receive `review_response_received` notifications.

## Reports

Table: `review_reports` with unique `(review_id, reporter_user_id)`.

Reasons: `spam`, `abuse`, `harassment`, `hate`, `fake`, `irrelevant`, `personal_information`, `other`.

Business managers and platform staff receive `review_reported` notifications.

## Privacy

Public reviews expose author `name` and `avatar_url` only. No phone, email, or internal reservation details.

## Authorization

| Action | Customer | Staff | Manager/Owner | Admin |
|---|---|---|---|---|
| Create review | Own completed reservation | — | — | — |
| Edit/delete own review | Yes | — | — | — |
| View management list | — | Yes | Yes | Yes |
| Respond | — | No | Yes | — |
| Moderate | — | — | — | Yes |
| Report | Yes | Yes | Yes | Yes |

## Concurrency

- `UNIQUE(reservation_id)` prevents duplicate reviews
- `UNIQUE(review_id, reporter_user_id)` prevents duplicate reports
- Transactions + `RatingSummaryService::syncBusinessCache()` keep aggregates consistent

## Analytics integration

Business analytics overview and `/analytics/reviews` expose average rating, distribution, period counts, response rate, and open reports — all via `RatingSummaryService` / `ReviewAnalyticsService`.

## Discovery integration

Discovery list and detail use cached `businesses.rating_average` and `rating_count`. Sort by `rating` is supported via indexed aggregate columns.

## Notifications

| Event | Recipient | Type |
|---|---|---|
| New review | Business managers | `review_created` |
| Business response | Customer | `review_response_received` |
| Review reported | Business managers | `review_reported` |

## Loyalty extension point

Review rewards are not auto-granted. Hook future loyalty actions to `ReviewCreated` with eligibility checks for published status.

## Audit

Moderation actions log: `review.published`, `review.hidden`, `review.rejected`, `review.restored`, `review.deleted`, `review.updated`.
