# Rezera API — PHP-FPM production image
FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl nginx libpq-dev libzip-dev libicu-dev libpng-dev \
        libjpeg62-turbo-dev libfreetype6-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql bcmath intl zip gd opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/zz-rezera.ini
COPY deploy/railway/nginx.conf /etc/nginx/sites-available/default
COPY deploy/railway/start-web.sh /usr/local/bin/rezera-web
RUN chmod +x /usr/local/bin/rezera-web

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

COPY deploy/docker/entrypoint.sh /usr/local/bin/rezera-entrypoint
RUN chmod +x /usr/local/bin/rezera-entrypoint \
    && sed -i 's/\r$//' /usr/local/bin/rezera-entrypoint

ENTRYPOINT ["rezera-entrypoint"]
CMD ["php-fpm", "-F"]
