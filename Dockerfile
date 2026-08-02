# ==================================================
# Stage 1: Frontend Asset Compilation (Node)
# ==================================================
FROM node:20-alpine AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ==================================================
# Stage 2: Backend Dependencies (Composer)
# ==================================================
FROM composer:2 AS composer-builder
WORKDIR /app
COPY composer*.json ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-autoloader \
    --no-scripts \
    --ignore-platform-reqs \
    --prefer-dist

COPY . .
RUN composer dump-autoload --no-dev --optimize

# ==================================================
# Stage 3: Final Production Image (PHP-FPM)
# ==================================================
FROM php:8.4-fpm-bookworm

# Install required system packages
RUN apt-get update && apt-get install -y \
    git \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install core PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring pcntl bcmath zip

# Install Redis driver
RUN pecl install redis && docker-php-ext-enable redis

# Set production configurations
COPY ./docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
# Override OPcache values for production performance
RUN echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/local.ini

# Setup application directory
WORKDIR /var/www/html

# Copy source and vendor from build stages
COPY --chown=www-data:www-data . .
COPY --from=composer-builder --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend-builder --chown=www-data:www-data /app/public/build ./public/build

# Expose FPM port
EXPOSE 9000

CMD ["php-fpm"]
