FROM php:8.3-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
COPY . .
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint

RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader \
    && chmod +x /usr/local/bin/docker-entrypoint

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint"]