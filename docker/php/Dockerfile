FROM php:8.4-fpm-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
# Note: zip is recommended for efficient Composer package extraction.
RUN docker-php-ext-install pdo_mysql mbstring pcntl bcmath zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get Composer (Pinned to major version 2)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy custom configurations
COPY ./docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]
