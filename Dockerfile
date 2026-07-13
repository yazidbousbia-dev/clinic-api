FROM php:8.2-cli

# ==============================
# System dependencies
# ==============================
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# ==============================
# PHP extensions needed by Laravel + MySQL
# ==============================
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    bcmath \
    xml \
    exif \
    pcntl

# ==============================
# Composer
# ==============================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ==============================
# App files
# ==============================
WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

# ==============================
# Permissions
# ==============================
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

# ==============================
# Run migrations, then start Laravel's built-in server
# bound to 0.0.0.0 and Railway's dynamic $PORT
# ==============================
CMD php artisan config:cache \
    && php artisan migrate --force \
    && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
