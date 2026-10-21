FROM php:8.3-cli-alpine

# Install system dependencies for PHP extensions & FrankenPHP
RUN apk add --no-cache \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    curl \
    git \
    unzip \
    bash \
    $PHPIZE_DEPS \
    linux-headers

# Configure and install PHP extensions (including phpredis)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        intl \
        bcmath \
        pcntl \
        posix \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers

# Production OPcache Configuration
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install Composer dependencies (Production optimized)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Ensure FrankenPHP binary is executable
RUN if [ -f /var/www/html/frankenphp ]; then chmod +x /var/www/html/frankenphp; fi

# Ensure storage directories exist and have proper permissions
RUN mkdir -p \
    /var/www/html/storage/app/private/biometrics \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD curl -f http://localhost:8080/up || exit 1

EXPOSE 8080

CMD ["sh", "-c", "php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8080 --admin-port=2019"]
