# Rezera — Beta Launch Runbook (Phase 10)

**Goal:** One city (Tashkent), a few clubs, real customers. Live bookings without double-book incidents; pay-at-venue understood.

## Package

| Artifact | Path |
|---|---|
| Deploy guide | [PRODUCTION_DEPLOYMENT.md](PRODUCTION_DEPLOYMENT.md) |
| Pre-flight checklist | [PRODUCTION_CHECKLIST.md](PRODUCTION_CHECKLIST.md) |
| Owner training (RU) | [OWNER_TRAINING_RU.md](OWNER_TRAINING_RU.md) |
| Security notes | [SECURITY.md](SECURITY.md) |
| Prod env template | [../.env.production.example](../.env.production.example) |
| Nginx sample | [../deploy/nginx-rezera.conf.example](../deploy/nginx-rezera.conf.example) |
| Supervisor sample | [../deploy/supervisor-rezera.conf.example](../deploy/supervisor-rezera.conf.example) |
| DB backup scripts | `scripts/backup-postgres.sh`, `scripts/backup-postgres.ps1` |
| Smoke command | `php artisan rezera:smoke` |

## Launch sequence (recommended)

1. **Staging**  
   - Copy `.env.production.example` → server `.env`  
   - `composer install --no-dev --optimize-autoloader`  
   - `php artisan migrate --force`  
   - `php artisan db:seed --class=Database\\Seeders\\BusinessCategorySeeder`  
   - `php artisan db:seed --class=Database\\Seeders\\SubscriptionPlanSeeder`  
   - **Do not** run `DemoClubSeeder` on production  
   - Configure Redis, HTTPS, supervisor, cron  
   - `php artisan rezera:smoke --strict`

2. **Mobile APK / release**  
   ```powershell
   # From repo root — HTTPS required
   .\scripts\build-release-apk.ps1 -ApiBaseUrl https://api.your-domain.uz
   ```  
   Or: `cd mobile && flutter build apk --release --dart-define=API_BASE_URL=https://api.your-domain.uz`  
   Release APK has cleartext **off**; LAN HTTP needs a debug APK (see `mobile/README.md`).

3. **Onboard 2–3 clubs**  
   - Owner registers in app → creates business → resources → hours  
   - Platform admin approves / verifies business  
   - Walk owners through [OWNER_TRAINING_RU.md](OWNER_TRAINING_RU.md)

4. **Soft beta (friends & regulars)**  
   - Customers book via app  
   - Staff confirm / check-in / check-out  
   - Watch `reservations:expire-pending` and queue workers  
   - Confirm no double-book (GiST exclusion + smoke)

5. **Go / no-go**  
   - [ ] Smoke green on production  
   - [ ] Backup restore tested once  
   - [ ] At least one full booking lifecycle per club  
   - [ ] Owners understand pay-at-venue (no in-app payment in MVP)

## Day-1 support

- Health: `GET https://api…/api/v1/health`
- Logs: `storage/logs/laravel.log` + Sentry
- Rollback: previous release + DB restore from last backup

## Explicitly out of beta MVP

- Real Payme/Click capture (pay at venue only)
- FlutterFire OS push (in-app inbox is enough)
- Play Store listing (internal APK OK for beta)
