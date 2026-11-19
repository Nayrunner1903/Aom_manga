
# Stage 1: Install Composer dependencies
FROM php:8.4-cli AS composer-deps

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    ca-certificates \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite mbstring zip intl \
    && rm -rf /var/lib/apt/lists/*

# Install Composer directly on PHP 8.4
RUN curl -fsSL https://getcomposer.org/installer \
        -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php \
        --install-dir=/usr/local/bin \
        --filename=composer \
    && rm /tmp/composer-setup.php \
    && test -s /usr/local/bin/composer \
    && composer --version

WORKDIR /app

COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    && test -f vendor/autoload.php \
    && php artisan package:discover --ansi


# Stage 2: Build frontend assets
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json* ./

RUN npm install

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

# Flux CSS and Livewire assets need Composer packages
COPY --from=composer-deps /app/vendor ./vendor

RUN npm run build


# Stage 3: Laravel runtime
FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite mbstring zip intl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY . .

COPY --from=composer-deps /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p \
    database \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data \
    storage bootstrap/cache database

EXPOSE 80

CMD ["apache2-foreground"]
