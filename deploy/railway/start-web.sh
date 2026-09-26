#!/bin/sh
set -eu

port="${PORT:-8080}"
case "$port" in
  *[!0-9]*|'') echo 'PORT must be numeric' >&2; exit 1 ;;
esac
sed -i "s/listen 8080;/listen ${port};/" /etc/nginx/sites-available/default
php-fpm -D
exec nginx -g 'daemon off;'
