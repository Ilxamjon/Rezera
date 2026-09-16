# Rezera — Docker server deploy

Kompyuterdagi `php artisan serve` o‘rniga API ni **Docker** orqali ishga tushirish (local yoki VPS).

## Nima ishlaydi

| Servis | Vazifa |
|--------|--------|
| `nginx` | HTTP (default `:8080`) |
| `app` | Laravel PHP-FPM |
| `worker` | Queue (`redis`) |
| `scheduler` | Cron o‘rniga `schedule:work` |
| `postgres` | PostgreSQL 16 + `btree_gist` |
| `redis` | Cache / queue / session |

## Tez start (local / VPS)

```bash
# 1) Env
cp .env.docker.example .env

# 2) APP_KEY (birinchi marta)
# Docker Desktop / Linux / VPS:
docker compose run --rm -e RUN_MIGRATIONS=0 -e CACHE_CONFIG=0 app php artisan key:generate --force

# 3) Ishga tushirish
docker compose up -d --build

# 4) Tekshirish
curl http://localhost:8080/api/v1/health
```

Admin panel: `http://localhost:8080/admin` (avval Filament user yarating).

## VPS ga qo‘yish (qisqa)

1. Ubuntu 22.04+ VPS oling (Hetzner, DigitalOcean, Timeweb, …).
2. Docker o‘rnating: https://docs.docker.com/engine/install/
3. Kodni yuklang:

```bash
git clone <sizning-repo> /opt/rezera
cd /opt/rezera
cp .env.docker.example .env
# .env ni tahrirlang: APP_URL, DB_PASSWORD, APP_KEY, CORS, …
docker compose up -d --build
```

4. Domen + HTTPS: reverse proxy (Caddy / Nginx + Certbot) `8080` ga yo‘naltirilsin.
5. Flutter APK:

```powershell
.\scripts\build-release-apk.ps1 -ApiBaseUrl https://api.sizning-domen.uz
```

## Foydali buyruqlar

```bash
docker compose logs -f app
docker compose exec app php artisan rezera:smoke
docker compose exec app php artisan rezera:publish-ready-businesses
docker compose down
```

## Muhim

- `.env` ni GitHub ga **commit qilmang**.
- Productionda `APP_DEBUG=false`, kuchli `DB_PASSWORD`, `APP_URL=https://…`.
- GitHub faqat kod saqlaydi; server VPS/PaaS da ishlaydi.

Batafsil: [PRODUCTION_DEPLOYMENT.md](PRODUCTION_DEPLOYMENT.md).
