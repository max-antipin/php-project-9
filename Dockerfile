FROM composer:2.9 AS composer_image

FROM php:8.4-cli

RUN set -eu; \
    apt-get update && apt-get install -y --no-install-recommends libzip-dev libpq-dev; \
    docker-php-ext-install zip pdo pdo_pgsql; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer_image --link /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --no-autoloader --no-progress --classmap-authoritative

CMD ["bash", "-c", "make start"]