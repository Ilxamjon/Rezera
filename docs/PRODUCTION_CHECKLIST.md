# Rezera Production Checklist

Use this checklist before every production deployment.

## Database

- [ ] PostgreSQL 16+ with `btree_gist` extension enabled
- [ ] Dedicated production database user with least privilege
- [ ] Separate database for staging and production
- [ ] Migrations applied: `php artisan migrate --force`
- [ ] Pending reservation expiry index present (`reservations_pending_expires_at_idx`)
- [ ] GiST exclusion constraint on `reservations.occupancy_range` verified
- [ ] Automated backups configured and restore tested
- [ ] Connection pooling configured (PgBouncer recommended)

## Environment

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` set and stored securely
- [ ] `APP_URL` matches public API URL
- [ ] `SANCTUM_TOKEN_EXPIRATION_MINUTES` set (default: 43200 / 30 days)
- [ ] `PAYMENT_MOCK_ALLOW_SIMULATION=false`
- [ ] `PAYMENT_MOCK_ENABLED=false`
- [ ] Real payment provider secrets configured (Payme/Click/Uzum/Stripe)
- [ ] `BUSINESS_*_REQUIRES_VERIFICATION` flags reviewed for launch policy

## Cache

- [ ] `CACHE_STORE=redis` (recommended) or database with monitoring
- [ ] Cache prefix unique per environment (`CACHE_PREFIX`)
- [ ] Tenant-scoped cache keys verified for dashboard/analytics

## Queue

- [ ] `QUEUE_CONNECTION=redis` or database with worker supervision
- [ ] Queue workers running with `--tries` and failure monitoring
- [ ] Notification jobs processing
- [ ] If push is live: `NOTIFICATION_PUSH_ENABLED=true`, `NOTIFICATION_PUSH_DRIVER=fcm`, FCM service account present
- [ ] Saved search alert jobs processing

## Scheduler

- [ ] Cron: `* * * * * php /path/to/artisan schedule:run`
- [ ] `reservations:expire-pending` running every minute
- [ ] `subscriptions:process-lifecycle` running hourly
- [ ] `saved-searches:process-alerts` running per config
- [ ] `loyalty:expire-points` scheduled if loyalty enabled

## Storage

- [ ] `FILESYSTEM_DISK` configured (S3-compatible for production uploads)
- [ ] Private disk for business documents / verification files
- [ ] Upload size limits enforced at reverse proxy

## Logging

- [ ] `LOG_LEVEL=warning` or `error` in production
- [ ] Centralized log aggregation (optional but recommended)
- [ ] `SENTRY_LARAVEL_DSN` set for staging/production
- [ ] No passwords, tokens, or payment secrets logged
- [ ] `SEED_DEMO_CLUB=false` (or omit) — never seed demo passwords in production

## Security

- [ ] HTTPS terminated at load balancer
- [ ] CORS origins restricted to known Flutter/web clients
- [ ] Rate limits reviewed (`API_RATE_LIMIT`, auth, booking, webhooks)
- [ ] Webhook signature verification enabled for all payment providers
- [ ] Platform admin accounts audited
- [ ] `.env` never committed

## Backups

- [ ] Daily PostgreSQL backups
- [ ] Point-in-time recovery tested
- [ ] Backup retention policy documented

## Monitoring

- [ ] Health endpoint monitored: `GET /api/v1/health` (alert on non-200 / `degraded`)
- [ ] `SENTRY_LARAVEL_DSN` receiving events from staging/production
- [ ] Mobile `SENTRY_DSN` set for release builds ([STORE_LISTING.md](STORE_LISTING.md))
- [ ] Queue failure alerts
- [ ] Database connection and disk alerts
- [ ] 5xx error rate alerts
- [ ] Scheduler last-run monitoring
- [ ] Privacy page live: `/privacy`

## Post-deploy smoke test

- [ ] `php artisan rezera:smoke --strict`
- [ ] Health endpoint: `GET /api/v1/health`
- [ ] Register / login / logout
- [ ] Create business (owner flow) + platform approve
- [ ] Create resource and availability check
- [ ] Create reservation (conflict protection / double-book blocked)
- [ ] Confirm/reject booking and see customer in-app notification
- [ ] Check-in / check-out (or QR) on device
- [ ] Business dashboard and Today ops load
- [ ] Owners briefed with [OWNER_TRAINING_RU.md](OWNER_TRAINING_RU.md)
- [ ] Backup script run once; restore tested on staging

## Beta go / no-go

- [ ] Follow [BETA_LAUNCH.md](BETA_LAUNCH.md) sequence
- [ ] Production APK built with HTTPS `API_BASE_URL`
- [ ] 2–3 clubs live in Tashkent
- [ ] Pay-at-venue explained (no in-app payment)