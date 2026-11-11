# Therapie Aktiv Register — production image (FrankenPHP, PHP 8.5, SQLite).
# Build and run with `docker compose up -d --build`; see compose.yaml.

FROM dunglas/frankenphp:1-php8.5-trixie

ARG APP_UID=1000
ARG APP_GID=1000

ENV SERVER_NAME=":8080" \
    COMPOSER_ALLOW_SUPERUSER=1

RUN install-php-extensions intl zip opcache \
    && apt-get update \
    && apt-get install -y --no-install-recommends libcap2-bin \
    && setcap -r /usr/local/bin/frankenphp \
    && apt-get purge -y libcap2-bin \
    && rm -rf /var/lib/apt/lists/* \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && php artisan filament:assets \
    && rm /usr/bin/composer

RUN groupadd --gid "$APP_GID" app \
    && useradd --uid "$APP_UID" --gid app --no-create-home --shell /usr/sbin/nologin app \
    && mkdir -p /srv/data storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/fonts \
    && chown -R app:app /srv/data storage bootstrap/cache /data/caddy /config/caddy \
    && chmod +x docker/entrypoint.sh

USER app

EXPOSE 8080
VOLUME ["/srv/data"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up > /dev/null || exit 1

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
