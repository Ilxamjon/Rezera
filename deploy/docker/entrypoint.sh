#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

# Keep storage/app writable when using a named volume
chown -R www-data:www-data storage/app 2>/dev/null || true

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
  echo "Waiting for database..."
  i=0
  until php -r '
    $host = getenv("DB_HOST") ?: "postgres";
    $port = getenv("DB_PORT") ?: "5432";
    $db = getenv("DB_DATABASE") ?: "rezera";
    $user = getenv("DB_USERNAME") ?: "rezera";
    $pass = getenv("DB_PASSWORD") ?: "";
    try {
      new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass, [
        PDO::ATTR_TIMEOUT => 2,
      ]);
      exit(0);
    } catch (Throwable $e) {
      exit(1);
    }
  '; do
    i=$((i + 1))
    if [ "$i" -ge 90 ]; then
      echo "Database not ready after 90s"
      exit 1
    fi
    sleep 1
  done

  php artisan migrate --force --no-interaction
  php artisan db:seed --class=Database\\Seeders\\BusinessCategorySeeder --force --no-interaction || true
  php artisan db:seed --class=Database\\Seeders\\SubscriptionPlanSeeder --force --no-interaction || true
fi

if [ "${CACHE_CONFIG:-1}" = "1" ] && [ -n "${APP_KEY:-}" ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

exec "$@"
