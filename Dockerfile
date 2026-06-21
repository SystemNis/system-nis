# ════════════════════════════════════════════════════════════════════════
# Dockerfile — NIS Audit System (Laravel 11 + Livewire 3)
# Built for Railway.com.
#
# Railway auto-detects and builds this Dockerfile. At runtime Railway
# injects $PORT — start.sh binds `php artisan serve` to 0.0.0.0:$PORT so
# Railway's edge proxy can reach the container. No Nginx/FPM needed; for
# this app's traffic profile (internal audit tool) the built-in server is
# sufficient and keeps the image simple to maintain.
# ════════════════════════════════════════════════════════════════════════

FROM php:8.3-cli-bookworm

# ── System dependencies ─────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libpng-dev \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ──────────────────────────────────────────────────────
# pdo_mysql / pdo_pgsql: for Railway's managed MySQL or PostgreSQL plugin
# pdo_sqlite: harmless fallback, matches local dev (Laravel Herd) setup
# zip, mbstring, xml, bcmath, gd: required by Laravel core + PhpSpreadsheet
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    zip \
    mbstring \
    xml \
    bcmath \
    gd \
    opcache

# ── Composer ─────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ── Install PHP dependencies first (better Docker layer caching) ────────
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# ── Copy application code ────────────────────────────────────────────────
COPY . .

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
               storage/logs bootstrap/cache database \
    && chmod -R 775 storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
