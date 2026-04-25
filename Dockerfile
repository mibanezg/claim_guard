# ─── Stage 1: Build Vite assets ───────────────────────────────────────────────
FROM node:22-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci --prefer-offline
COPY . .
RUN npm run build

# ─── Stage 2: Production image ─────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS production

# System packages
RUN apk add --no-cache \
    nginx \
    supervisor \
    bash \
    curl \
    git \
    zip \
    unzip \
    # GD / image
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    # intl
    icu-dev \
    # zip
    libzip-dev \
    # mbstring
    oniguruma-dev \
    # Chromium — requerido por spatie/laravel-pdf v2 (Browsershot)
    chromium \
    chromium-chromedriver \
    fontconfig \
    ttf-freefont

# PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        intl \
        zip \
        bcmath \
        mbstring \
        opcache \
        pcntl \
        exif

# Redis (PECL) — compilar y limpiar headers de build
RUN apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .phpize-deps

# Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencias PHP (sin dev, autoloader optimizado)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Código de la aplicación
COPY . .

# Assets compilados por Node
COPY --from=node-builder /app/public/build ./public/build

# Scripts post-install de Composer (necesitan el código completo)
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# Archivos de configuración Docker
COPY docker/nginx/default.conf      /etc/nginx/http.d/default.conf
COPY docker/php/local.ini           /usr/local/etc/php/conf.d/zz-local.ini
COPY docker/php/www.conf            /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh           /entrypoint.sh

# Permisos y directorios de log
RUN mkdir -p /var/log/supervisor /var/log/nginx \
    && chown -R www-data:www-data \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache \
    && chmod -R 775 \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache \
    && chmod +x /entrypoint.sh

# Chromium sin sandbox para entornos Docker
ENV CHROMIUM_EXECUTABLE=/usr/bin/chromium-browser
ENV CHROME_FLAGS="--no-sandbox --disable-dev-shm-usage"

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

ENTRYPOINT ["/entrypoint.sh"]
