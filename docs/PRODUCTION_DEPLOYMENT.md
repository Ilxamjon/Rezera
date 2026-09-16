# Rezera Production Deployment

## Requirements

- PHP 8.2+ with extensions: `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- PostgreSQL 16+ with `btree_gist`
- Composer 2.x
- Redis (recommended for cache + queue)
- Nginx (or equivalent) + PHP-FPM
- Supervisor or systemd for queue workers
- TLS certificate (Let's Encrypt)

See also: [BETA_LAUNCH.md](BETA_LAUNCH.md), [PRODUCTION_CHECKLIST.md](PRODUCTION_CHECKLIST.md), [OWNER_TRAINING_RU.md](OWNER_TRAINING_RU.md).

## Initial setup

```bash
cd /var/www/rezera
composer install --no-dev --optimize-autoloader
cp .env.production.example .env   # or copy from secure secret store
php artisan key:generate

# Edit .env: DB_*, REDIS_*, APP_URL=https://..., APP_DEBUG=false, SEED_DEMO_CLUB=false

php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\BusinessCategorySeeder --force
php artisan db:seed --class=Database\\Seeders\\SubscriptionPlanSeeder --force
# Do NOT run DemoClubSeeder on production

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

php artisan rezera:smoke --strict
```

Sample configs:

- **Docker (recommended for VPS):** [DOCKER_DEPLOY.md](DOCKER_DEPLOY.md), `docker-compose.yml`
- Nginx: `deploy/nginx-rezera.conf.example`
- Supervisor: `deploy/supervisor-rezera.conf.example`
- Env template: `.env.production.example` / `.env.docker.example`

## Queue worker

```bash
php artisan queue:work redis --tries=3 --timeout=120
```

Prefer Supervisor (`deploy/supervisor-rezera.conf.example`) with `numprocs=2`.

## Scheduler

Crontab (as deploy user):

```cron
* * * * * cd /var/www/rezera && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled commands include:

- `reservations:expire-pending` — every minute
- `subscriptions:process-lifecycle` — hourly
- `saved-searches:process-alerts` — configurable interval
- `saved-searches:cleanup-alerts` — daily

## Backups

```bash
# Linux
export PGPASSWORD=...
DB_NAME=rezera DB_USER=rezera_app ./scripts/backup-postgres.sh

# Windows (ops laptop)
$env:PGPASSWORD='...'
.\scripts\backup-postgres.ps1
```

Restore (custom format):

```bash
pg_restore -h 127.0.0.1 -U rezera_app -d rezera_restore --clean --if-exists path/to/file.dump
```

Test restore on a **non-production** database at least once before beta.

## Health & smoke

```
GET /api/v1/health
```

Response includes `status` (`ok` | `degraded`) plus `checks.database` and `checks.redis` (Redis skipped when unused). Uptime monitors should alert on non-200.

```
php artisan rezera:smoke
php artisan rezera:smoke --strict   # production go/no-go
```

## Monitoring

| Signal | How |
|---|---|
| Uptime | Probe `GET /api/v1/health` every 1–5 minutes |
| Errors (API) | `SENTRY_LARAVEL_DSN` in `.env` (`config/sentry.php`) |
| Errors (app) | Flutter `SENTRY_DSN` dart-define / env (see `mobile/README.md`) |
| Queues | Supervisor workers + failed_jobs table |
| Backups | `scripts/backup-postgres.sh` / `.ps1` daily; test restore quarterly |

## Privacy

Public policy page: `/privacy` (required for Play / App Store listings).

## Mobile release against production API

```powershell
# Repo root — rejects non-HTTPS URLs
.\scripts\build-release-apk.ps1 -ApiBaseUrl https://api.example.uz
```

```bash
cd mobile
flutter build apk --release --dart-define=API_BASE_URL=https://api.example.uz
```

Release APK disables cleartext HTTP. LAN testing: `flutter build apk --debug --dart-define=API_BASE_URL=http://…`.

Use HTTPS only. Cleartext LAN builds are for local QA.

## Testing before deploy

```bash
# Requires PostgreSQL; use dedicated rezera_test database
composer test
```

## Zero-downtime tip

1. Put new code in a release directory  
2. `composer install --no-dev` + migrate  
3. Switch symlink `current` → new release  
4. `php artisan queue:restart`  
5. Reload php-fpm  

See [PRODUCTION_CHECKLIST.md](PRODUCTION_CHECKLIST.md) for the full pre-launch checklist.
