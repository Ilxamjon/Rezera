# Rezera Security Architecture

## Authentication

- **Laravel Sanctum** bearer tokens for mobile API
- Tokens expire after `SANCTUM_TOKEN_EXPIRATION_MINUTES` (default 30 days)
- Logout revokes the current token only; consider token pruning for compromised accounts
- Auth endpoints rate-limited (`auth` limiter: IP + phone)

## Authorization model

Business-scoped actions require:

```
Authenticated User
+ Active Business Membership
+ Role permission (owner / manager / staff)
+ Policy check
```

Platform admin routes use `platform.admin` middleware plus granular permissions.

## Multi-tenancy & IDOR protection

- **Route model binding** scopes nested resources to `{business}` in `RouteBindingServiceProvider`
- **Policies** enforce membership and role on manage routes
- **Form requests** validate resource ownership (e.g. `ResourceBelongsToBusiness`)
- **Actions** perform defense-in-depth checks before writes

Cross-tenant ID swapping on `/manage/businesses/{business}/…` routes returns 404.

## Reservation safety

- Resource row `lockForUpdate()` during create
- `ReservationConflictService` buffer-aware overlap check
- PostgreSQL GiST `EXCLUDE` on `(resource_id, occupancy_range)` for occupying statuses
- `reservations:expire-pending` scheduled job releases stale pending holds

## Payments

- Webhook signature verification required
- Idempotency via `event_id` on webhook events
- Mock provider disabled in production by default
- Never trust client-supplied totals; pricing computed server-side

## Sensitive data

- API resources exclude password hashes and internal secrets
- Platform settings block secret key updates via API
- Payment payloads sanitized before persistence

## Rate limiting

| Limiter | Default |
|---------|---------|
| `api` | 60/min per user or IP |
| `auth` | 10/min per IP + phone |
| `booking` | 20/min per user or IP |
| `webhooks` | 120/min per provider + IP |

## Production hardening

- Set `APP_DEBUG=false`
- Configure real payment webhook secrets
- Use Redis for cache/queue in production
- Restrict CORS to known client origins
- Run PostgreSQL on private network only
- Set `SENTRY_LARAVEL_DSN` for error tracking
- Seed demo clubs only in non-production (`SEED_DEMO_CLUB` / `DemoClubSeeder`)
