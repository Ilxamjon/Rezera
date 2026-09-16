# Rezera API

Mobile-first booking and reservation platform for Uzbekistan. This repository contains the **Laravel REST API** that powers the Rezera Flutter application.

## Stack

- **PHP** 8.2+
- **Laravel** 11
- **PostgreSQL** 16+ (required for production)
- **Laravel Sanctum** (API token authentication)
- **Flutter** mobile client in [`mobile/`](mobile/) (Phase 6 customer app)

## Documentation

| Document | Purpose |
|---|---|
| [docs/PRD.md](docs/PRD.md) | Product requirements entry point |
| [docs/PRD-MVP-BLUEPRINT.md](docs/PRD-MVP-BLUEPRINT.md) | Full PRD and MVP blueprint |
| [docs/DATABASE-ARCHITECTURE.md](docs/DATABASE-ARCHITECTURE.md) | PostgreSQL schema design |
| [docs/BACKEND_ARCHITECTURE.md](docs/BACKEND_ARCHITECTURE.md) | Backend conventions and structure |
| [docs/DATABASE_IMPLEMENTATION.md](docs/DATABASE_IMPLEMENTATION.md) | Applied migrations and models |
| [docs/AUTHENTICATION.md](docs/AUTHENTICATION.md) | Auth API and authorization |
| [docs/BUSINESS_MANAGEMENT.md](docs/BUSINESS_MANAGEMENT.md) | Business & venue management API |
| [docs/RESOURCE_MANAGEMENT.md](docs/RESOURCE_MANAGEMENT.md) | Resource inventory management API |
| [docs/AVAILABILITY_ENGINE.md](docs/AVAILABILITY_ENGINE.md) | Availability calculation API |
| [docs/RESERVATION_ENGINE.md](docs/RESERVATION_ENGINE.md) | Reservation / booking API |
| [docs/BOOKING_MANAGEMENT.md](docs/BOOKING_MANAGEMENT.md) | Booking management & business operations |
| [docs/PAYMENT_ARCHITECTURE.md](docs/PAYMENT_ARCHITECTURE.md) | Payment abstraction foundation |
| [docs/NOTIFICATION_ARCHITECTURE.md](docs/NOTIFICATION_ARCHITECTURE.md) | Notification & communication foundation |
| [docs/ADMIN_PANEL.md](docs/ADMIN_PANEL.md) | Platform admin API foundation |
| [docs/SEARCH_AND_DISCOVERY.md](docs/SEARCH_AND_DISCOVERY.md) | Customer search & discovery API |
| [docs/FAVORITES_AND_PROFILE.md](docs/FAVORITES_AND_PROFILE.md) | User profile & business favorites |
| [docs/FAVORITES_AND_WISHLIST.md](docs/FAVORITES_AND_WISHLIST.md) | Favorites / wishlist engine |
| [docs/SAVED_SEARCHES_AND_ALERTS.md](docs/SAVED_SEARCHES_AND_ALERTS.md) | Saved searches & availability alerts |
| [docs/REVIEWS_AND_RATINGS.md](docs/REVIEWS_AND_RATINGS.md) | Reviews & ratings API |
| [docs/PROMO_CODES_AND_DISCOUNTS.md](docs/PROMO_CODES_AND_DISCOUNTS.md) | Promo codes, coupons & discounts |
| [docs/PRICING_ENGINE.md](docs/PRICING_ENGINE.md) | Advanced pricing engine |
| [docs/CHECK_IN_CHECK_OUT.md](docs/CHECK_IN_CHECK_OUT.md) | QR check-in, check-out & on-site operations |
| [docs/BETA_LAUNCH.md](docs/BETA_LAUNCH.md) | Phase 10 beta launch runbook |
| [docs/PRODUCTION_DEPLOYMENT.md](docs/PRODUCTION_DEPLOYMENT.md) | Production deploy, workers, backups |
| [docs/DOCKER_DEPLOY.md](docs/DOCKER_DEPLOY.md) | Docker Compose server (VPS / local) |
| [docs/STORE_LISTING.md](docs/STORE_LISTING.md) | Play/App Store assets, privacy, deep links |
| [docs/PRODUCTION_CHECKLIST.md](docs/PRODUCTION_CHECKLIST.md) | Pre-launch / go-no-go checklist |
| [docs/OWNER_TRAINING_RU.md](docs/OWNER_TRAINING_RU.md) | Owner training sheet (Russian) |
| [docs/SECURITY.md](docs/SECURITY.md) | Security architecture notes |

## Requirements

- PHP 8.2 or newer with extensions: `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- Composer 2.x
- PostgreSQL 16+ (local development and tests)

## Local setup

```bash
# 1. Install dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure PostgreSQL in .env
# DB_CONNECTION=pgsql
# DB_DATABASE=rezera
# DB_USERNAME=rezera
# DB_PASSWORD=your_password

# 4. Run migrations + seed categories/plans (+ demo clubs in local)
php artisan migrate
php artisan db:seed
# or only demo: php artisan db:seed --class=Database\\Seeders\\DemoClubSeeder

# 5. Start the API (LAN devices need 0.0.0.0 — default binds 127.0.0.1 only)
php artisan serve --host=0.0.0.0 --port=8000
# or: composer run serve:lan
```

Health check: `GET http://localhost:8000/api/v1/health`

### Demo accounts (after `DemoClubSeeder`)

| Role | Phone | Password |
|---|---|---|
| Club owner (Neon Arena) | `+998901000001` | `password` |
| Staff | `+998901000002` | `password` |
| Customer | `+998901000003` | `password` |
| Club owner (Pixel Hub, manual) | `+998901000004` | `password` |
| Platform admin | `+998901000009` | `password` |

Neon Arena: overnight hours 12:00–06:00, instant confirm. Pixel Hub: 24/7, manual confirm.

### Authentication (mobile)

```http
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/logout   # Bearer token required
GET  /api/v1/auth/me       # Bearer token required
```

See [docs/AUTHENTICATION.md](docs/AUTHENTICATION.md) for auth endpoints and [docs/BUSINESS_MANAGEMENT.md](docs/BUSINESS_MANAGEMENT.md) for business management endpoints.

## Tests

PostgreSQL is required for integration tests that depend on database features (especially exclusion constraints).

```bash
# Create databases
createdb rezera
createdb rezera_test

# Migrate and seed categories
php artisan migrate
php artisan db:seed

# Run tests
composer test
```

## Code formatting

```bash
composer format
composer format:test
```

## API versioning

All mobile endpoints are under `/api/v1/`.

## Payment

| Component | Status |
|---|---|
| Payment foundation & abstraction | Implemented |
| Mock provider (dev/test) | Implemented |
| Payme | Planned |
| Click | Planned |
| Uzum | Planned |
| Stripe | Planned |

## Notifications

| Component | Status |
|---|---|
| In-app notifications | Implemented |
| Preferences & devices API | Implemented |
| Push/SMS/Telegram abstraction | Implemented (mock providers) |
| Firebase FCM | Implemented (HTTP v1, opt-in) |
| Real SMS providers | Planned |
| Telegram Bot API | Planned |

## Platform Admin

| Component | Status |
|---|---|
| Admin API foundation | Implemented |
| Dashboard aggregates | Implemented |
| User/business management | Implemented |
| Audit logs | Implemented |
| Web admin UI | Planned |

## Discovery

| Component | Status |
|---|---|
| Business search & filters | Implemented |
| Geo / nearest sorting | Implemented |
| Availability-based discovery | Implemented |
| Category discovery with counts | Implemented |
| Favorite state in discovery | Implemented |
| External search engine | Planned |

## Profile & Favorites

| Component | Status |
|---|---|
| Customer profile API | Implemented |
| Business favorites | Implemented |
| Phone change verification | Planned |
| Favorite counts on discovery | Planned |

## Reviews & Ratings

| Component | Status |
|---|---|
| Review creation (completed reservations) | Implemented |
| Business responses | Implemented |
| Admin moderation | Implemented |
| Rating aggregates in discovery | Implemented |
| AI moderation | Planned |

## License

Proprietary — Rezera.

## Beta launch (Phase 10)

Operational package for first-city launch:

```bash
php artisan rezera:smoke --strict
composer run seed:demo   # local/QA only — never on production
```

Full steps: [docs/BETA_LAUNCH.md](docs/BETA_LAUNCH.md).
