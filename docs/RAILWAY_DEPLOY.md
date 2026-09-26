# Rezera on Railway (limited beta)

Use one Railway project with four services: Postgres, API, worker, scheduler.
All three application services point to this GitHub repository and use the same Dockerfile.
Keep each application service at **one replica** until scheduling and storage are moved to shared services.

## 1. PostgreSQL

Create a Railway PostgreSQL service. The Rezera migrations install `btree_gist`; deployment
will stop if the database user cannot create the extension. Do not bypass that migration:
the exclusion constraint prevents overlapping reservations.

## 2. Shared variables

Set these on the three application services (or use Railway shared variables):

| Variable | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Generate locally with `php artisan key:generate --show`; keep private |
| `APP_URL` | `https://<API Railway domain>` |
| `DB_CONNECTION` | `pgsql` |
| `DB_URL` | `${{Postgres.DATABASE_URL}}` (adjust service name if different) |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `SESSION_DRIVER` | `database` |
| `LOG_CHANNEL` | `stderr` |
| `PAYMENT_MOCK_ENABLED` | `false` |
| `PAYMENT_MOCK_ALLOW_SIMULATION` | `false` |
| `SEED_DEMO_CLUB` | `false` |
| `REZERA_OTP_EXPOSE_DEBUG_CODE` | `false` |

Never put real keys or passwords in GitHub. For the beta, customers pay at the venue.
The SMS OTP route is disabled with a mock provider in production; password login remains.

## 3. API

Connect `Ilxamjon/Rezera`, repository root `/`, Dockerfile `Dockerfile`.
The root `railway.json` sets the web start command and `/api/v1/health` check.
Set `RUN_MIGRATIONS=1` only here, and `CACHE_CONFIG=1`.
Generate a public Railway domain; set `APP_URL` to it. Set replicas to one.
The container starts Nginx on Railway's `$PORT` and PHP-FPM internally.

## 4. Worker and scheduler

Create two more services from the same GitHub repository. In each service's settings,
set **Railway Config File** to its absolute path in the repository:

- Worker: `/deploy/railway/worker.json`
- Scheduler: `/deploy/railway/scheduler.json`

Set `RUN_MIGRATIONS=0` and `CACHE_CONFIG=0` on both. Do not generate public domains.
Deploy these after the API migrations have completed. Use one scheduler replica only.

## 5. Verify and connect mobile

Run `php artisan rezera:smoke` from the API service shell. Check the public
`https://<API Railway domain>/api/v1/health` URL. Make and cancel a reservation on two
physical phones, confirm the owner receives it, and check the worker and scheduler logs.
Build the Android APK with `scripts/build-release-apk.ps1 -ApiBaseUrl https://<API Railway domain>`.
The current APK is signed with a debug key; configure release signing before Play Store.

Before inviting customers, configure backups and test a restore, complete the remaining
items in `PRODUCTION_CHECKLIST.md`, and verify a full real booking lifecycle.
